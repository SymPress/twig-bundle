<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use Twig\Markup;

/**
 * Inline SVG files from the active theme, child theme first.
 *
 * Theme files are developer-owned, but exported SVGs routinely carry scripts,
 * editor metadata and external references. Every file therefore passes an
 * element and attribute allowlist before it crosses the Html boundary.
 *
 * @internal
 */
final class Svg
{
    private const string NAMESPACE = 'http://www.w3.org/2000/svg';
    private const int MAX_BYTES = 512 * 1024;
    private const string UNSAFE_CSS = '/expression\s*\(|image-set\s*\(|src\s*\(|@import|javascript:/i';

    /** Drawing, grouping and local references only: no script, style, foreignObject, image, a or animation. */
    private const array ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop', 'clipPath', 'mask', 'pattern', 'marker',
        'filter', 'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feDropShadow',
        'feFlood', 'feFuncA', 'feFuncB', 'feFuncG', 'feFuncR', 'feGaussianBlur', 'feMerge',
        'feMergeNode', 'feMorphology', 'feOffset',
    ];

    /** Geometry and presentation attributes. Event handlers and editor namespaces never match. */
    private const array ATTRIBUTES = [
        'id', 'class', 'style', 'role', 'focusable', 'version', 'viewBox', 'preserveAspectRatio',
        'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy',
        'd', 'points', 'pathLength', 'transform', 'href', 'xlink:href',
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity',
        'opacity', 'color', 'display', 'visibility', 'overflow', 'vector-effect', 'shape-rendering', 'paint-order',
        'clip-path', 'clip-rule', 'clipPathUnits', 'mask', 'maskUnits', 'maskContentUnits',
        'filter', 'filterUnits', 'primitiveUnits', 'offset', 'stop-color', 'stop-opacity',
        'gradientUnits', 'gradientTransform', 'spreadMethod', 'patternUnits', 'patternContentUnits', 'patternTransform',
        'marker-start', 'marker-mid', 'marker-end', 'markerWidth', 'markerHeight', 'markerUnits',
        'refX', 'refY', 'orient',
        'font-family', 'font-size', 'font-style', 'font-weight', 'text-anchor', 'dominant-baseline',
        'letter-spacing', 'word-spacing', 'dx', 'dy', 'rotate', 'textLength', 'lengthAdjust', 'startOffset',
        'in', 'in2', 'result', 'mode', 'values', 'type', 'operator', 'k1', 'k2', 'k3', 'k4', 'stdDeviation',
        'flood-color', 'flood-opacity', 'radius', 'tableValues', 'slope', 'intercept', 'amplitude', 'exponent',
    ];

    /** @var array<string, string> Sanitized markup by path and modification time. */
    private static array $sanitized = [];

    /** @param array<array-key, mixed> $attributes Root attributes from templates; null removes one. */
    public static function inline(string $name, array $attributes = []): Markup
    {
        $path = self::locate($name);
        $markup = $path === null ? null : self::sanitizedFile($path);
        if ($markup === null) {
            $message = sprintf('The SVG "%s" is missing, outside the theme or invalid.', $name);
            wp_trigger_error(__METHOD__, $message, E_USER_WARNING);
            return Html::trusted('');
        }

        $document = new \DOMDocument();
        $document->loadXML($markup, LIBXML_NONET);
        $document->encoding = 'UTF-8';
        $root = $document->documentElement;
        if (!$root instanceof \DOMElement) {
            return Html::trusted('');
        }
        foreach ($attributes as $attribute => $value) {
            $attribute = (string) $attribute;
            if ($value !== null && !is_scalar($value) && !$value instanceof \Stringable) {
                throw new \InvalidArgumentException(sprintf('The SVG attribute "%s" needs a text value.', $attribute));
            }
            // ARIA states need "true"/"false"; PHP would render true as "1".
            $value = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            if (!self::allows($attribute, (string) $value)) {
                throw new \InvalidArgumentException(sprintf('The SVG attribute "%s" is not allowed.', $attribute));
            }
            $value === null ? $root->removeAttribute($attribute) : $root->setAttribute($attribute, (string) $value);
        }
        // Icons are decorative unless the template or the file names them.
        if (!self::isNamed($root) && !$root->hasAttribute('aria-hidden')) {
            $root->setAttribute('aria-hidden', 'true');
        }

        return Html::trusted((string) $document->saveXML($root));
    }

    private static function locate(string $name): ?string
    {
        $segment = '[\pL\pN_-][\pL\pN_.-]*';
        if (!preg_match('~^' . $segment . '(?:/' . $segment . ')*$~u', $name) || str_contains($name, '..')) {
            throw new \InvalidArgumentException('SVG names must be relative theme paths without traversal.');
        }
        $path = realpath(get_theme_file_path(str_ends_with($name, '.svg') ? $name : $name . '.svg'));
        if ($path === false || !is_file($path)) {
            return null;
        }
        // Symlinks may not lead out of the child or parent theme.
        foreach (array_unique([get_stylesheet_directory(), get_template_directory()]) as $theme) {
            $root = realpath($theme);
            if ($root !== false && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
                return $path;
            }
        }
        return null;
    }

    private static function sanitizedFile(string $path): ?string
    {
        $key = $path . ':' . (int) filemtime($path);
        if (isset(self::$sanitized[$key])) {
            return self::$sanitized[$key];
        }
        $size = filesize($path);
        $xml = $size === false || $size > self::MAX_BYTES ? false : file_get_contents($path);
        $markup = $xml === false ? null : self::sanitize($xml);
        if ($markup !== null) {
            self::$sanitized[$key] = $markup;
        }
        return $markup;
    }

    private static function sanitize(string $xml): ?string
    {
        // Entities are never substituted (no LIBXML_NOENT); declaring any is refused outright.
        if (stripos($xml, '<!ENTITY') !== false) {
            return null;
        }
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        // Keep text such as umlauts in titles as UTF-8 instead of character references.
        $document->encoding = 'UTF-8';
        $root = $document->documentElement;
        // Also catches declarations the byte check above misses, e.g. in UTF-16 files.
        if (!$loaded || ($document->doctype->internalSubset ?? '') !== '') {
            return null;
        }
        if (!$root instanceof \DOMElement || $root->localName !== 'svg' || !self::isAllowedElement($root)) {
            return null;
        }
        self::clean($root);
        $markup = $document->saveXML($root);
        return $markup === false ? null : $markup;
    }

    private static function clean(\DOMElement $element): void
    {
        // Without preserved keys: the map is keyed by local name, so onload and x:onload would collide.
        foreach (iterator_to_array($element->attributes, false) as $attribute) {
            if (self::allows($attribute->nodeName, $attribute->value)) {
                continue;
            }
            $element->removeAttributeNode($attribute);
        }
        foreach (iterator_to_array($element->childNodes, false) as $child) {
            if ($child instanceof \DOMElement && self::isAllowedElement($child)) {
                self::clean($child);
            } elseif ($child instanceof \DOMCdataSection) {
                $element->replaceChild(new \DOMText($child->data), $child);
            } elseif (!$child instanceof \DOMText) {
                // Comments, processing instructions, entity references and foreign elements.
                $element->removeChild($child);
            }
        }
    }

    private static function isNamed(\DOMElement $root): bool
    {
        return $root->hasAttribute('aria-label')
            || $root->hasAttribute('aria-labelledby')
            || $root->hasAttribute('role')
            || array_any(
                iterator_to_array($root->childNodes, false),
                static fn (\DOMNode $node): bool => $node->localName === 'title',
            );
    }

    private static function isAllowedElement(\DOMElement $element): bool
    {
        return in_array($element->namespaceURI, [null, self::NAMESPACE], true) && in_array($element->localName, self::ELEMENTS, true);
    }

    private static function allows(string $name, string $value): bool
    {
        if (!in_array($name, self::ATTRIBUTES, true) && !preg_match('/^aria-[a-z]+$/', $name)) {
            return false;
        }
        if ($name === 'href' || $name === 'xlink:href') {
            return str_starts_with(trim($value), '#');
        }
        // CSS escapes could hide url() or expression(); values never need backslashes.
        // image-set() and src() load external images without url().
        if (str_contains($value, '\\') || preg_match(self::UNSAFE_CSS, $value)) {
            return false;
        }
        // Paint servers, clip paths, masks and filters may only reference this document.
        preg_match_all('/url\s*\(\s*[\'"]?\s*([^\'")\s]*)/i', $value, $targets);
        return array_all($targets[1], static fn (string $target): bool => str_starts_with($target, '#'));
    }
}
