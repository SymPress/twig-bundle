# WordPress themes

The WordPress adapter uses the existing Symfony Twig environment. It never boots
a kernel or installs WordPress. Ordinary Twig usage is unchanged without an
active registered classic theme; frontend interception excludes regular admin requests and block themes.
WordPress functions, filters, `site`, and the shared WordPress layout are available
in regular admin requests, including mail rendering. Custom-template discovery
also runs there so the editor lists Twig templates; `@theme` and frontend
interception remain disabled. Helpers can be initialized during MU-plugin boot
before theme directories are registered; block-theme inspection waits for theme setup.
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
theme bundles cannot activate their settings. Frontend activation happens at
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
its own template loader. Captures from earlier plugin getters are reset at the
end of `template_redirect`. The bundle never replays template getters. A PHP
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
Secondary loops change post globals while retaining the main `wp_query`, so
conditional tags and content plugins keep the page's original query. Explicit
query-context construction is scoped separately. Use two passes over the repeatable
collection for a featured post followed by a grid, with complete wrappers in each pass.

Each readonly `Post` wraps `wpPost` and exposes `id`, `title`, `url`, `type`,
`protected`, `date(format)`, `date_iso`, `content`, `excerpt(words)`,
`thumbnail(size, attributes)`, `categories`, `author`, `terms(taxonomy)` and
explicit `meta(key)`. There is no
magic fallback from unknown properties to metadata. Content, excerpts and images
are lazy; content is filtered once per object. Protected content produces the
WordPress password form; protected metadata/images are withheld. Excerpts never
expose password-protected stored text, including to unlocked visitors. Author
and taxonomy lookups are lazy and memoized; protected posts return null/empty
results. Unknown taxonomies return an empty list. Term access uses configured models.

`author` in archive context and `post.author` return a public `User` profile with
`id`, `name`, `slug`, `url`, `description` and `avatar(size)`. Safe compatibility
aliases are `ID`, `display_name` and `user_nicename`. The profile retains no native
`WP_User`, email, password hash, roles, capabilities or account metadata. Supply
any intentionally private data separately through an authorized PHP composer.

Extend the readonly Post class with `#[WordPress\Attribute\AsPostModel('event')]`
on an autoconfigured class. `AsTermModel('genre')` similarly selects a readonly
`Term` subclass. Subclasses must remain readonly. Constructors take the native
`WP_Post`/`WP_Term` as their first argument, followed by required services and
optional defaults. The container resolves typed registered dependencies, explicit
service arguments and bindings; a missing required dependency fails compilation.
Post constructors may request `MetaResolverInterface` and `TermFactory` and pass
them to the parent constructor. Models are created per native object, rather than
as singleton services; injected dependencies are resolved lazily. Duplicate mappings
fail container compilation. `PostFactory` also applies post models to ACF relations.
Term models expose `id`, `name`, `slug`, `taxonomy`, `url` and the native `wpTerm`.

Template composers implement `TemplateComposerInterface::compose(TemplateContext): array`
and use `#[AsTemplateComposer(templates: ['single', 'page-*'], priority: 10)]`.
Patterns match hierarchy names. A composer runs once if any pattern matches;
lower priorities merge first, higher priorities override them. Untargeted interface
implementations match `*`. `TemplateContext` exposes `template`, `candidates`,
`data` and `post()`. Plugins without DI can use `sympress/twig/context` and
`sympress/twig/template_candidates`.

Matching uses the candidate list, not only the resolved template. Native page
hierarchies always contain `index`, so `templates: ['index']` runs on every page.
Use `*` for an explicit global composer; inspect `TemplateContext::template` when
logic depends on the resolved file. Explicit render calls match their supplied
candidates; the implicit index fallback is only used for template lookup.
Debug template comments are appended only to `renderCurrent()`, never to
`render()` or `renderBlock()` fragments.

## Menus, pagination and WordPress helpers

`menu('primary', {depth: 2})` returns a `Menu` with `items`. Items provide `id`,
`title`, `link`, `current`, `classes`, `children`, `target`, `rel`, `description`
and `attr_title`. Additional native menu arguments such as `menu_class` are
accepted. WordPress prepares the menu and current-state classes and runs
`wp_nav_menu_objects` with its complete argument object; the collector applies
`the_title`, `nav_menu_item_title`, `nav_menu_item_args` and `nav_menu_css_class`.
The object API owns its walker and output options. HTML-output filters are not
object transforms; a `pre_wp_nav_menu` short-circuit or a filter replacing the
collector walker produces no collected objects. Use `wp_nav_menu(args)` for
plugins supplying their own walkers or fully rendered markup.
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
`size_format`, `wp_date` and WordPress-aware `date`. Use WordPress escaping as
filters, e.g. `value|esc_url`; Twig's standard escape strategies remain upstream.
The custom WordPress strategies such as `e('esc_url')` were withdrawn in 1.1.2
because they double-escaped output under HTML autoescaping. Date formatting
uses `wp_date`, the WordPress timezone and the configured date format by default.
Translations accept an optional final textdomain and are not marked safe.
There is no generic PHP-function or database-query helper. `action(name, ...args)`
does dispatch any registered WordPress action and captures its output as trusted
HTML. Treat templates as application code: action handlers can have side effects
and must authorize their operations and escape their output. Never take action
names or arguments from unvalidated user input. The raw-filter lint is not a sandbox.

Prefer `value|wp_date(format, timezone)` for WordPress date formatting. The `date`
alias remains available for compatibility and overrides Twig's built-in `date`
filter when the WordPress layer is active.
`DateInterval` values delegate to Twig's interval formatter (including its default
interval format). `timezone: false` retains a DateTime object's timezone; for
timestamps, strings and null it uses the WordPress timezone. Other date values
default to the site timezone and WordPress date format rather than Twig defaults.

`value|wpautop` escapes ordinary strings as HTML before adding paragraph markup.
For example, `<strong>text</strong>` in an ordinary string is shown as text,
not a bold element. Explicitly trusted Twig markup keeps its existing HTML;
never mark arbitrary input trusted. This protection requires HTML autoescaping,
which is the default for theme templates; do not disable it for untrusted input.

`value|shortcodes` expands registered WordPress shortcodes, then sanitizes the
entire result with `wp_kses_post()` before exposing it as safe HTML. Allowed
post markup survives; script tags, event-handler attributes and unsafe URL
protocols do not. This also applies to HTML returned by shortcode callbacks,
including explicitly trusted input and templates with autoescaping disabled.
Callbacks still execute normally and remain responsible for authorization and
side effects. Shortcodes needing scripts must enqueue them through WordPress.

These restrictions were introduced in 1.1.1 to close an HTML-escaping bypass.
Themes relying on arbitrary HTML in `wpautop` input or script output from
`shortcodes` must adapt to these boundaries.

Text/HTML filters accept `null` as an empty string, including missing ACF fields.
`time_ago` and `size_format` return an empty string for `null`. The `date` and
`wp_date` filters retain Twig's existing `null`-means-now convention.

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
