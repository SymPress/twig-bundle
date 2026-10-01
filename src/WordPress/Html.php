<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use Twig\Markup;

/** The single trust boundary for HTML produced by WordPress. */
final class Html
{
    public static function trusted(string $html): Markup
    {
        return new Markup($html, 'UTF-8');
    }

    public static function text(string $text): string
    {
        return html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function capture(callable $callback): string
    {
        ob_start();
        try {
            $callback();
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
