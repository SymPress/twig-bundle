# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
where applicable.

## 1.2.0 - 2026-10-01

- Make WordPress helpers and the shared document layout available to registered
  themes in admin requests and during early MU-plugin rendering. Keep frontend
  theme paths and template interception isolated from regular admin requests.
- Preserve the main query during secondary post loops, and reset captured
  template candidates before WordPress begins frontend template resolution.
- Add public-only User profiles, lazy Post author/taxonomy accessors, and
  container-injected dependencies for readonly post and term models.
- Collect menu objects through the native WordPress menu pipeline, including
  full filter arguments, item-title filters, descriptions and title attributes.
- Support DateInterval formatting through Twig and retain object timezones when
  date/wp_date receives timezone: false.
- Limit debug comments to renderCurrent(); explicit renders and blocks stay clean.
- Clarify composer candidate matching and the trust boundary of action().
- Test these behaviors with compiled containers and real WordPress, including
  early bootstrap, admin rendering, nested queries and native menu filters.

## 1.1.2 - 2026-10-01

- Withdraw custom WordPress escape strategies that double-escaped entities under
  Twig HTML autoescaping. Use `value|esc_url` and the other WordPress filters
  instead of `value|e('esc_url')`; standard Twig strategies remain unchanged.
- Accept null text/HTML input as an empty string and return empty output for null
  `time_ago`/`size_format` values, avoiding errors for missing metadata/ACF fields.
- Test URL entity preservation and nullable filters with Twig 3.30 and real WordPress.

## 1.1.1 - 2026-10-01

- Escape untrusted `wpautop` input before paragraph formatting and sanitize
  shortcode output with `wp_kses_post()`. Scripts, event handlers and unsafe URL
  protocols are no longer exposed as safe HTML; shortcode scripts must be enqueued.
- Restrict PHPStan paths to source, unit tests and fixtures so an installed
  WordPress integration fixture cannot recurse through its self-referencing symlink.
- Add `wp_date` as an explicit WordPress date filter, retaining the `date` alias.
- Cover these HTML boundaries with Twig rendering tests and real WordPress checks.
- Limit coding-standard exceptions to the specific files that require them.

## 1.1.0 - 2026-10-01

- Add an opt-in WordPress theme layer with active-slug selection, child-first
  `@theme` paths, native hierarchy capture, PHP/plugin precedence, custom templates,
  block rendering and a shared document layout.
- Add lazy posts, post/term model attributes, loop restoration, query context,
  template composers, menu/pagination objects and optional ACF metadata conversion.
- Add lazy WordPress Twig runtimes, WordPress date formatting, explicit escaping
  strategies and a reusable lint rule rejecting the `raw` filter in themes.

- Register custom page templates in admin requests as well as the frontend.
- Preserve composer attribute patterns and priorities when interface autoconfiguration also adds a tag.

## 1.0.x

- Symfony TwigBundle integration, SymPress renderer services and global-provider support.
