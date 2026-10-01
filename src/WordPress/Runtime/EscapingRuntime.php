<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress\Runtime;

use Twig\Attribute\AsTwigFilter;

/** @internal */
final class EscapingRuntime
{
    #[AsTwigFilter('esc_html', isSafe: ['html'])]
    public function html(string $value): string
    {
        return esc_html($value);
    }

    #[AsTwigFilter('esc_attr', isSafe: ['html'])]
    public function attr(string $value): string
    {
        return esc_attr($value);
    }

    #[AsTwigFilter('esc_url', isSafe: ['html'])]
    public function url(string $value): string
    {
        return esc_url($value);
    }

    #[AsTwigFilter('esc_js', isSafe: ['html'])]
    public function js(string $value): string
    {
        return esc_js($value);
    }

    #[AsTwigFilter('wp_kses_post', isSafe: ['html'])]
    public function post(string $value): string
    {
        return wp_kses_post($value);
    }

    /**
     * @param array<string, array<string, bool>>|string $allowed
     */
    #[AsTwigFilter('wp_kses', isSafe: ['html'])]
    public function kses(string $value, array|string $allowed): string
    {
        return wp_kses($value, $allowed);
    }
}
