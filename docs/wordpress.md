# WordPress themes

The WordPress adapter uses the existing Symfony Twig environment. It never boots
a kernel or installs WordPress. Ordinary Twig usage is unchanged without an
active registered classic theme; frontend interception excludes regular admin requests and block themes.
Custom-template discovery also runs in admin requests so the editor lists Twig templates.
AJAX requests may render explicitly even when WordPress reports an admin context.

## Activation

Register the installed theme directory slug from its bundle, before extensions
load. All configuration except the slug is optional:

```php
public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
{
    $container->prependExtensionConfig('sympress_twig', ['wordpress' => ['themes' => [
        'my-theme' => [
            'views_dir' => 'resources/views',
            'page_templates_dir' => 'custom',
            'text_domain' => null,
        ],
    ]]]);
}
```

The runtime selects `get_stylesheet()` first, then `get_template()`. A child theme
without a registration inherits the parent's configuration. Other installed
theme bundles cannot activate their settings. Selection happens at
`after_setup_theme`, after WordPress has loaded the theme. A theme may declare
`add_theme_support('sympress-twig')` in its setup as a capability marker; the
bundle registration is the activation mechanism. Theme support alone does not
register container services.

Site options under `sympress_twig.wordpress` are `hook_prefix` (default
`sympress/twig`), `template_include` (true), and `debug_comment` (kernel debug).
Twig's `auto_reload` defaults to `WP_DEBUG` when defined, unless explicitly set.
View directories must be safe relative paths. Textdomain defaults to the active
theme's `Text Domain` header; a registration can override it.

## Templates and PHP precedence

`@theme` searches the child view directory before its parent's directory.
`@wordpress/document.html.twig` provides the document and native lifecycle hooks,
with `head` and `body` blocks. Themes normally add a `content` block in their layout.

Template hierarchy filters are observed at `PHP_INT_MAX` while WordPress runs
its own template loader. The bundle never replays its template getters. A PHP
template outside the theme, or an explicitly selected unranked PHP template,
is retained. A more specific PHP candidate wins; equal specificity prefers Twig.
The selected Twig template is rendered through the bundle's PHP include stub.
Missing specific Twig templates fall back through the hierarchy to `index`.

Place custom templates in `resources/views/custom/*.html.twig`:

```twig
{#
Template Name: Campaign
Template Post Type: page, event
#}
{% extends '@theme/layouts/base.html.twig' %}
```

Without `Template Post Type`, the template is available to pages. Child templates
override parent headers with the same relative filename. Candidate names reject
absolute paths, traversal, namespaces, NUL bytes and stream prefixes. Safe
relative subdirectories are supported for custom templates and partials.

Inject `WordPress\ThemeRenderer` for `renderCurrent()`, `render(['single-event',
'single', 'singular'], $context)`, `renderBlock('singular', 'content', $context)`
and `resolved()`. Names are hierarchy names, optionally with `.html.twig` or
`.php` suffixes, rather than namespace-qualified paths. `TemplateHierarchy::forQuery()`
builds candidates for explicit REST/AJAX/CLI queries without replaying native
template getters. Pass explicit query context with
`QueryContextProvider::context($query)`. PHP theme controllers can return or echo
`SymPress\TwigBundle\WordPress\render('singular', $context)`.

## Context and models

The query context contains `posts`, singular `post`, archive `term`/`author`,
`title`, archive `description`, `search_query` and `show_comments`. It is memoized
by query for the request. UI-specific titles, such as a translated search heading,
belong in the theme. `site` is a lazy `AsTwigGlobal` object with `name`,
`description`, `url`, `charset`, `logo` and `language`.

`posts` is countable and repeatable. Iterate it directly in Twig: native
`WP_Query::the_post()` establishes plugin-compatible globals and loop actions.
The prior globals and query position are restored on completion, early break,
exception and nested loops. Regular admin iteration does not modify loop globals;
explicit AJAX rendering uses the same scoped loop behavior as frontend rendering.
Avoid `|slice` or converting the collection to an array when template tags need
the active loop; these operations materialize the iterator before rendering.
Use `loop.first` to create featured-post layouts within one loop.

Each readonly `Post` wraps `wpPost` and exposes `id`, `title`, `url`, `type`,
`protected`, `date(format)`, `date_iso`, `content`, `excerpt(words)`,
`thumbnail(size, attributes)`, `categories` and explicit `meta(key)`. There is no
magic fallback from unknown properties to metadata. Content, excerpts and images
are lazy; content is filtered once per object. Protected content produces the
WordPress password form; protected metadata/images are withheld. Excerpts never
expose password-protected stored text, including to unlocked visitors.

Extend the readonly Post class with `#[WordPress\Attribute\AsPostModel('event')]`
on an autoconfigured class. `AsTermModel('genre')` similarly selects a readonly
`Term` subclass. Constructors retain the base model contract. Duplicate mappings
fail container compilation. `PostFactory` also applies post models to ACF relations.
Term models expose `id`, `name`, `slug`, `taxonomy`, `url` and the native `wpTerm`.

Template composers implement `TemplateComposerInterface::compose(TemplateContext): array`
and use `#[AsTemplateComposer(templates: ['single', 'page-*'], priority: 10)]`.
Patterns match hierarchy names. A composer runs once if any pattern matches;
lower priorities merge first, higher priorities override them. Untargeted interface
implementations match `*`. `TemplateContext` exposes `template`, `candidates`,
`data` and `post()`. Plugins without DI can use `sympress/twig/context` and
`sympress/twig/template_candidates`.

## Menus, pagination and WordPress helpers

`menu('primary', {depth: 2})` returns a `Menu` with `items`. Items provide `id`,
`title`, `link`, `current`, `classes`, `children`, `target` and `rel`.
`pagination({prev_text: __('Back'), next_text: __('Next')})` provides `pages`,
`next`, `prev`, `current`; each link has `title`, `url`, `current` (gaps have a null
URL). Themes own all markup. Use `wp_nav_menu(args)` for WordPress-rendered HTML.

Lazy runtimes expose `wp_head`, `wp_footer`, `wp_body_open`,
`language_attributes`, `body_class`, `post_class`, `sidebar`, `search_form`,
`wp_link_pages`, `the_password_form`, `comments_template`, `action`, `sprintf`,
`__`, `_x`, `_n`, `_nx`, `is_front_page`, `is_home`, `is_singular`, `is_archive`,
`has_nav_menu`. Comments enqueue `comment-reply` when needed.

Filters include `esc_html`, `esc_attr`, `esc_url`, `esc_js`, `wp_kses_post`,
`wp_kses`, `wpautop`, `shortcodes`, `stripshortcodes`, `excerpt`, `time_ago`,
`size_format` and WordPress-aware `date`. The first five escaping filters are
also available as escape strategies, e.g. `value|e('esc_url')`. Date formatting
uses `wp_date`, the WordPress timezone and the configured date format by default.
Translations accept an optional final textdomain and are not marked safe.
Arbitrary PHP function calls and database-query template helpers are absent.

## Escaping and ACF

PHP supplies plain text. Twig escapes it. URLs enter model data through
`esc_url_raw`, not entity-encoding URL functions. Only WordPress-rendered HTML
crosses `Html::trusted()` or a runtime's explicitly safe HTML function/filter.
Never mark arbitrary user input trusted. Theme lint environments should register
`WordPress\Lint\NoRawFilter` as a node visitor to reject `|raw`, including in
partials and macros. This is a lint rule, not a PHP/Twig sandbox.

`MetaResolverInterface` is replaceable through DI. The default implementation
uses native post meta unless ACF's `get_field_object` exists. ACF image/gallery
values become `Image` objects; post-object/relationship fields become Post
objects; date/date-time fields become `DateTimeImmutable` using the field's return
format and WordPress timezone. Invalid dates return null. Other values preserve
ACF's formatted return value, including nested repeater/group structures.
`Image` exposes `id`, `url(size)`, `alt` and `html(size, attributes)` using native
registered image sizes; it never writes resized files.

Behavior was checked against the native WordPress template loader and
[Timber's loop lifecycle](https://github.com/timber/timber/blob/0281b39/src/PostsIterator.php).
The implementation uses WordPress APIs directly and does not depend on Timber.
ACF contracts follow [get_field_object](https://www.advancedcustomfields.com/resources/get_field_object/)
and [Date Picker](https://www.advancedcustomfields.com/resources/date-picker/).
