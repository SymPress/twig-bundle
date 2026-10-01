# Public API and deprecation policy

The WordPress layer introduced in 1.1 is covered by the package's 1.x SemVer
promise. Its contracts are described in [wordpress.md](wordpress.md).

## Supported extension surface

- `sympress_twig.wordpress`: `themes` keyed by installed slug, per-theme
  `views_dir`, `page_templates_dir`, `text_domain`; global `hook_prefix`,
  `template_include`, `debug_comment`. Symfony's own `twig` options remain upstream API.
- `{hook_prefix}/template_candidates`: receives the candidate list; return a list.
  `{hook_prefix}/context`: receives context and resolved Twig template; return context.
- `TemplateComposerInterface`, `TemplateContext`, `AsTemplateComposer`,
  `AsPostModel`, `AsTermModel`, readonly `Post` and `Term` base model contracts.
- `PostFactory::from()`, `TermFactory::from()`, `QueryContextProvider::context()`,
  `TemplateHierarchy::forQuery()`, `ThemeRenderer::render()`, `renderCurrent()`,
  `renderBlock()`, `resolved()`, and the namespaced `render()` function.
- The documented query context, `Site`, `PostCollection` iteration/count,
  `Post`/`Term` accessors, `Image`, `Menu`/`MenuItem`,
  `Pagination`/`PaginationLink` and `MetaResolverInterface`.
- All Twig functions and filters listed in the WordPress
  reference; `@theme` child-first lookup; `@wordpress/document.html.twig` and
  its `head`/`body` blocks; `Lint\NoRawFilter` for lint environments.
- Existing non-WordPress renderer, global-provider and bundle APIs remain supported.
- `Excerpt::fromPost()`, `Excerpt::plainExcerpt()` and the explicit `Html::trusted()` boundary.

Use factories or Twig helpers for value objects. Their internal construction,
service wiring, runtime classes, hierarchy capture and PHP interception are not
extension points. Classes marked `@internal` may change in minor releases.
Undocumented methods of the supported services are implementation details.

Menu and pagination markup belongs in templates. ACF support is optional inside
this package, enabled by availability of `get_field_object()`; no ACF dependency
is installed. Native metadata remains the fallback. Nested ACF arrays retain
ACF formatting; only top-level documented field types receive object conversion.

## Changes and deprecations

Patch releases fix bugs without removing supported APIs. Minor releases may add
optional features. Breaking supported changes require a new major release.
Deprecations are announced in the changelog and upgrade guide with a replacement,
remain available through the current major, and are removed no earlier than the
next major. A security fix may restrict unsafe input; its impact is documented.
The unsafe custom WordPress escape strategies were withdrawn in 1.1.2; use the
documented WordPress filters instead. Twig's own escape strategies are unchanged.
There is no promise of compatibility for undocumented/internal implementation
details, WordPress plugin internals, or changes in upstream supported APIs.

## Production cache lifecycle

Compile with the production WordPress configuration. `auto_reload` defaults to
`WP_DEBUG` during container compilation; changing debug or Twig options requires
a container rebuild. Do not reuse a development container in production.

Install the site's locked dependencies and compile assets in a new release
directory. Give each deployment a unique `SYMPRESS_KERNEL_BUILD_ID`, or remove
that release's `var/cache/<environment>/kernel` before boot. Clear the configured
Twig cache separately when reusing a release directory; its path is the `twig.cache`
setting. Never clear a shared live cache while requests are writing it.

Warm the container by booting WordPress through the site's normal MU plugin under
the production environment, then request representative frontend URLs (home,
singular, archive and custom templates). This warms the actual frontend Twig
extension and loop path; an admin or generic CLI-only request is insufficient.
Switch traffic atomically only after successful HTTP checks. Retain the prior
release and its caches for rollback. The bundle does not provide a standalone
Symfony console or invent a `cache:warmup` command for WordPress.
