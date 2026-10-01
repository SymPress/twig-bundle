# Upgrade to 1.1.2

Replace WordPress custom escape strategies with their HTML-safe filters:

```twig
{# Before: double escaping under HTML autoescaping #}
{{ url|e('esc_url') }}
{# After #}
{{ url|esc_url }}
```

The same replacement applies to `esc_html`, `esc_attr`, `esc_js` and
`wp_kses_post`. WordPress strategies are no longer registered; continued use
raises Twig's unknown-strategy error. Twig's own `html`, `html_attr`, `js`, `css`
and `url` strategies are unaffected. These filters still require the appropriate
HTML/attribute/JavaScript context; Twig templates are trusted application code.

All nullable text/HTML filter input is normalized to an empty string.
`time_ago` and `size_format` return empty output for null metadata; `date` and
`wp_date` retain the existing null-means-now behavior.

The HTML-filter security boundaries from 1.1.1 remain in effect. Rebuild the
container and compiled Twig template cache during deployment, following
`docs/public-api.md`.
