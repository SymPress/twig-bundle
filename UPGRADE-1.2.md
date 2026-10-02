# Upgrade to Twig Bundle 1.2

Install 1.2 through Composer and rebuild the site's container and Twig cache as
described in [the production lifecycle](docs/public-api.md#production-cache-lifecycle).
An environment initialized during MU-plugin boot now exposes WordPress helpers
throughout the request, including admin rendering. Regular admin requests still
do not load `@theme` paths or intercept `template_include`; the shared
`@wordpress/document.html.twig` layout is available.

Author archive context now contains a public `User` profile rather than a raw
`WP_User`. `ID`, `display_name` and `user_nicename` remain available; prefer `id`,
`name`, `slug`, `url`, `description` and `avatar`. Email, password hashes, roles
and arbitrary user metadata are unavailable. This restriction closes accidental
disclosure through templates. Add deliberately authorized account data in PHP.
`post.author` exposes the same profile and `post.terms(taxonomy)` returns configured
term models. Both accessors are lazy, memoized and withhold protected-post data.

Readonly Post/Term subclasses remain compatible. To inject services, keep the
native WordPress object as the first constructor argument, then add typed
dependencies, explicit arguments/bindings and optional defaults. Forward Post's
meta resolver and optional term factory to its parent constructor. Required
unresolved dependencies fail container compilation; models remain per-object
instances, not singleton container services.

Secondary loops retain the main global query. Code relying on related-post loops
to change global conditional tags must query its own WP_Query explicitly.
`render()` and `renderBlock()` no longer append page debug comments; only
`renderCurrent()` does. Composer patterns still match all supplied hierarchy
candidates: `index` is global for native page rendering.

Object menus run the native menu preparation pipeline and title filters. Items
add `description` and `attr_title`. HTML-output filters and custom walkers belong
to the `wp_nav_menu()` HTML helper; replacing the collector or short-circuiting
the native menu produces no object tree. `DateInterval` formatting delegates to
Twig; `timezone: false` preserves a DateTime object's zone. Prefer `wp_date` over
the compatible WordPress `date` alias.

The security restrictions in 1.1.1/1.1.2 are retained: wpautop escapes untrusted
strings, shortcodes sanitize HTML, nullable filters accept missing metadata,
and WordPress escaping uses filters such as `esc_url`, not `e('esc_url')`.
`action()` remains a trusted application-code boundary, not a template sandbox.
