<?php
declare(strict_types=1);

use SymPress\Kernel\App;
use SymPress\TwigBundle\WordPress\{Menu, MenuWalker, PostCollection, PostScope, QueryContextProvider, User};
use SymPress\TwigFixture\{InjectedPost, InjectedTerm, EarlyRenderer};

$before = $checks;
$saved = PostScope::snapshot();
$originalMain = $GLOBALS['wp_the_query'];
$locations = get_theme_mod('nav_menu_locations', []);
$ids = [];
$userId = 0;
$termId = 0;
$menuId = 0;
$queryFilter = null;
$objectsFilter = null;
$titleFilter = null;
try {
    $userId = wp_insert_user(['user_login' => 'twig-v12-' . wp_generate_password(8, false), 'user_pass' => 'private-fixture-password', 'user_email' => 'private-v12@example.test', 'display_name' => 'Public fixture author']);
    $assert(is_int($userId), 'Disposable author was created.');
    foreach (['main', 'related'] as $name) {
        $ids[] = wp_insert_post(['post_status' => 'publish', 'post_title' => 'V12 ' . $name, 'post_content' => 'V12 content', 'post_author' => $userId]);
    }
    $mainQuery = new WP_Query(['p' => $ids[0]]);
    $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = $mainQuery;
    $queryFilter = static function (string $html) use ($assert, $mainQuery): string {
        $assert($GLOBALS['wp_query'] === $mainQuery && is_singular(), 'Related-content plugins retain main-query conditionals.');
        return $html;
    };
    add_filter('the_content', $queryFilter);
    foreach (new PostCollection(new WP_Query(['p' => $ids[1]]), $factory) as $related) {
        $assert($GLOBALS['wp_query'] === $mainQuery && get_the_ID() === $ids[1], 'Standalone secondary loop keeps the main query and installs its post.');
        $related->content();
    }
    foreach (new PostCollection($mainQuery, $factory) as $outer) {
        foreach (new PostCollection(new WP_Query(['p' => $ids[1]]), $factory) as $related) {
            $assert($GLOBALS['wp_query'] === $mainQuery && get_the_ID() === $ids[1], 'Nested related loop keeps the main query.');
            break;
        }
        $assert(get_the_ID() === $outer->id(), 'Nested secondary-loop break restores the outer post.');
    }
    $post = $factory->from(get_post($ids[0]));
    $assert($post instanceof InjectedPost && $post->label() === 'injected', 'The real compiled container injects post-model dependencies.');
    $author = $post->author();
    $assert($author instanceof User && $author->name() === 'Public fixture author', 'Post author is a safe public profile.');
    $assert($post->author() === $author, 'Author conversion is memoized.');
    $assert(!property_exists($author, 'user_email') && !property_exists($author, 'user_pass'), 'Private user fields are absent.');
    $assert(!str_contains(serialize($author), get_userdata($userId)->user_pass), 'Serialization does not expose a password hash.');
    $contextAuthor = App::make(QueryContextProvider::class)->context(new WP_Query(['author' => $userId]))['author'];
    $assert($contextAuthor instanceof User, 'Author archives expose the same public model.');
    $twig = App::make(EarlyRenderer::class)->twig;
    $assert($twig->createTemplate('{{ author.user_pass|default("hidden") }}|{{ author.user_email|default("hidden") }}|{{ author.name }}')->render(['author' => $contextAuthor]) === 'hidden|hidden|Public fixture author', 'Twig cannot access private account data.');
    $term = wp_insert_term('Twig model ' . wp_generate_password(8, false), 'category');
    $termId = $term['term_id'];
    wp_set_post_terms($ids[0], [$termId], 'category');
    $terms = $post->terms('category');
    $assert(count($terms) === 1 && $terms[0] instanceof InjectedTerm && $terms[0]->label() === 'injected', 'Taxonomy access uses DI-aware term models.');
    $assert($post->terms('category') === $terms && $post->terms('missing_taxonomy') === [], 'Terms are memoized and missing taxonomies are empty.');

    register_nav_menu('v12-fixture', 'Fixture');
    $menuId = wp_create_nav_menu('Twig object menu ' . wp_generate_password(8, false));
    $parent = wp_update_nav_menu_item($menuId, 0, ['menu-item-object-id' => $ids[0], 'menu-item-object' => 'post', 'menu-item-type' => 'post_type', 'menu-item-title' => 'Parent', 'menu-item-description' => 'Description', 'menu-item-attr-title' => 'Tooltip', 'menu-item-status' => 'publish']);
    wp_update_nav_menu_item($menuId, 0, ['menu-item-type' => 'custom', 'menu-item-url' => 'https://example.test/child', 'menu-item-title' => 'Child', 'menu-item-parent-id' => $parent, 'menu-item-status' => 'publish']);
    set_theme_mod('nav_menu_locations', [...$locations, 'v12-fixture' => $menuId]);
    $objectsFilter = static function (array $items, object $args) use ($assert): array {
        $assert($args->menu instanceof WP_Term && $args->walker instanceof MenuWalker && $args->menu_class === 'fixture-menu', 'Native object filters receive the complete WordPress argument object.');
        return $items;
    };
    $titleFilter = static function (string $title, WP_Post $item, object $args, int $depth) use ($assert): string {
        $assert($args->theme_location === 'v12-fixture' && $depth >= 0, 'Native item-title filters receive location and depth.');
        return $title . ' filtered';
    };
    add_filter('wp_nav_menu_objects', $objectsFilter, 10, 2);
    add_filter('nav_menu_item_title', $titleFilter, 10, 4);
    $menu = Menu::at('v12-fixture', ['depth' => 2, 'menu_class' => 'fixture-menu']);
    $assert(count($menu->items) === 1 && $menu->items[0]->title === 'Parent filtered', 'Object menus retain title filters and tree structure.');
    $assert($menu->items[0]->current && in_array('current-menu-item', $menu->items[0]->classes, true), 'Native current-item detection is preserved.');
    $assert($menu->items[0]->description === 'Description' && $menu->items[0]->attr_title === 'Tooltip', 'Menu descriptions and title attributes are exposed.');
    $assert(count($menu->items[0]->children) === 1 && $menu->items[0]->children[0]->title === 'Child filtered', 'Nested menu title filters run.');
    $assert(Menu::at('v12-fixture', ['depth' => 1, 'menu_class' => 'fixture-menu'])->items[0]->children === [], 'Menu depth limits are native-compatible.');
    $date = new DateTimeImmutable('2026-10-01 12:00:00', new DateTimeZone('Asia/Tokyo'));
    $assert($twig->createTemplate('{{ value|wp_date("H:i P", false) }}')->render(['value' => $date]) === '12:00 +09:00', 'wp_date retains object timezone when explicitly false.');
    $assert($twig->createTemplate('{{ value|date("%d days") }}')->render(['value' => new DateInterval('P2D')]) === '2 days', 'DateInterval delegates to Twig formatting.');
    $hierarchy->capture(['foreign-plugin.php']);
    do_action('template_redirect');
    $assert($hierarchy->current() === ['index'], 'Native template_redirect resets captures from earlier plugin getters.');
    echo 'PASS: ' . ($checks - $before) . " WordPress 1.2 feature integration checks.\n";
} finally {
    if ($queryFilter) { remove_filter('the_content', $queryFilter); }
    if ($objectsFilter) { remove_filter('wp_nav_menu_objects', $objectsFilter); }
    if ($titleFilter) { remove_filter('nav_menu_item_title', $titleFilter); }
    if ($menuId) { wp_delete_nav_menu($menuId); }
    set_theme_mod('nav_menu_locations', $locations);
    unregister_nav_menu('v12-fixture');
    foreach ($ids as $id) { wp_delete_post($id, true); }
    if ($termId) { wp_delete_term($termId, 'category'); }
    if (is_int($userId) && $userId) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($userId); }
    $GLOBALS['wp_the_query'] = $originalMain;
    PostScope::restore($saved);
}
