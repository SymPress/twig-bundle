<?php

declare(strict_types=1);

use SymPress\Kernel\App;
use SymPress\TwigBundle\WordPress\PostCollection;
use SymPress\TwigBundle\WordPress\PostFactory;
use SymPress\TwigBundle\WordPress\PostScope;
use SymPress\TwigBundle\WordPress\QueryContextProvider;
use SymPress\TwigBundle\WordPress\ThemeRenderer;

if (wp_get_environment_type() !== 'local' || $GLOBALS['table_prefix'] !== 'twig_') {
    throw new RuntimeException('Restricted to the disposable twig_ fixture.');
}
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($message); }
};
$saved = PostScope::snapshot();
$main = $GLOBALS['wp_the_query'];
$ids = [];
$contentCalls = [];
$starts = 0;
$ends = 0;
$started = static function () use (&$starts): void { ++$starts; };
$ended = static function () use (&$ends): void { ++$ends; };
$content = static function (string $html) use (&$contentCalls, $assert): string {
    $contentCalls[] = get_the_ID();
    $assert(in_the_loop(), 'Content filters see in_the_loop=true.');
    return $html;
};
try {
    foreach (['first', 'second'] as $name) {
        $ids[] = wp_insert_post(['post_status' => 'publish', 'post_title' => 'Twig loop ' . $name, 'post_content' => '<p>' . $name . '</p>']);
    }
    $query = new WP_Query(['post__in' => $ids, 'orderby' => 'post__in']);
    $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = $query;
    $factory = App::make(PostFactory::class);
    $posts = new PostCollection($query, $factory);
    $assert(count($posts) === 2, 'Counting does not consume the loop.');
    add_action('loop_start', $started);
    add_action('loop_end', $ended);
    add_filter('the_content', $content);
    $snapshot = PostScope::snapshot();
    foreach ($posts as $item) {
        $assert(get_the_ID() === $item->id(), 'Template tags see the current lazy post.');
        $assert($contentCalls === [], 'Iterating/title access does not run the_content.');
        $item->title();
    }
    $assert($starts === 1 && $ends === 1, 'Native loop actions fire once.');
    $assert(PostScope::snapshot() === $snapshot, 'Full iteration restores globals.');
    foreach ($posts as $item) {
        $nested = new PostCollection(new WP_Query(['p' => $ids[1]]), $factory);
        foreach ($nested as $inner) {
            $assert(get_the_ID() === $inner->id(), 'Nested loop installs its post.');
        }
        $assert(get_the_ID() === $item->id(), 'Nested loop restores the outer post.');
        $item->content();
        $item->content();
    }
    $assert($contentCalls === $ids, 'Each lazy content is filtered once under its own global post.');
    $assert(PostScope::snapshot() === $snapshot, 'Nested rendering restores globals.');
    $startBefore = $starts;
    $endBefore = $ends;
    foreach ($posts as $item) { break; }
    $assert($starts === $startBefore + 1 && $ends === $endBefore + 1, 'Early termination emits loop_end.');
    $assert(PostScope::snapshot() === $snapshot, 'Early termination restores globals.');
    try {
        foreach ($posts as $item) { throw new RuntimeException('intentional loop failure'); }
    } catch (RuntimeException $error) {
        $assert($error->getMessage() === 'intentional loop failure', 'Loop exception propagates.');
    }
    $assert(PostScope::snapshot() === $snapshot, 'Exception restores globals.');
    $provider = App::make(QueryContextProvider::class);
    $assert($provider->context($query)['posts'] === $provider->context($query)['posts'], 'Query context is memoized.');
    $renderer = App::make(ThemeRenderer::class);
    $block = $renderer->renderBlock('index', 'content', ['posts' => $posts, 'show_comments' => false]);
    $assert(str_contains($block, 'Twig loop first') && !str_contains($block, '<html'), 'renderBlock returns only the selected block.');
    $assert(PostScope::snapshot() === $snapshot, 'Block rendering restores loop state.');
    echo "PASS: {$checks} lazy-loop and block-rendering integration checks.\n";
} finally {
    remove_action('loop_start', $started);
    remove_action('loop_end', $ended);
    remove_filter('the_content', $content);
    foreach ($ids as $id) { wp_delete_post($id, true); }
    $GLOBALS['wp_the_query'] = $main;
    PostScope::restore($saved);
}

$hierarchy = App::make(\SymPress\TwigBundle\WordPress\TemplateHierarchy::class);
$hierarchy->capture(['index.php']);
$selected = apply_filters('template_include', get_template_directory() . '/index.php');
$assert(str_ends_with($selected, '/Resources/wordpress/template.php'), 'Native template_include selects Twig.');
$assert(trim($renderer->render('custom/landing')) === 'child landing', 'Child views override parent views.');
echo "PASS: native template interception and child override.\n";
