<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

/** Explicit boundary between WordPress-rendered HTML and plain template data. */
final class Excerpt
{
    public static function text(string $value): string
    {
        return Html::text($value);
    }

    public static function fromPost(\WP_Post $post, int $words = 30): string
    {
        if ($post->post_password !== '') {
            return '';
        }
        $seen = [];
        $remaining = 100;
        $allowReusable = static fn (array $allowed): array => array_values(array_unique([...$allowed, 'core/block']));
        $renderReusable = static function (?string $rendered, array $block) use (&$seen, &$remaining): ?string {
            return self::reusableExcerpt($rendered, $block, $seen, $remaining);
        };
        add_filter('excerpt_allowed_blocks', $allowReusable);
        add_filter('pre_render_block', $renderReusable, 10, 2);
        try {
            return wp_trim_words(self::text(get_the_excerpt($post)), $words, '…');
        } finally {
            remove_filter('excerpt_allowed_blocks', $allowReusable);
            remove_filter('pre_render_block', $renderReusable, 10);
        }
    }

    /**
     * Expand reusable text through the same block allowlist as ordinary excerpts.
     * Never run an embedded query/form block just to construct a card excerpt.
     *
     * @param array{blockName?: ?string, attrs?: array<string, mixed>} $block
     * @param array<int, true> $seen
     */
    private static function reusableExcerpt(?string $rendered, array $block, array &$seen, int &$remaining): ?string
    {
        if ($rendered !== null || ($block['blockName'] ?? '') !== 'core/block') {
            return $rendered;
        }
        $reference = (int) ($block['attrs']['ref'] ?? 0);
        if ($reference === 0 || isset($seen[$reference]) || count($seen) >= 20 || $remaining <= 0) {
            return '';
        }
        --$remaining;
        $reusable = get_post($reference);
        if (
            !$reusable instanceof \WP_Post || $reusable->post_type !== 'wp_block'
            || $reusable->post_status !== 'publish' || $reusable->post_password !== ''
        ) {
            return '';
        }
        $seen[$reference] = true;
        try {
            return excerpt_remove_blocks(strip_shortcodes($reusable->post_content));
        } finally {
            unset($seen[$reference]);
        }
    }

    public static function plainExcerpt(\WP_Post $post, int $words = 30): string
    {
        if ($post->post_password !== '') {
            return '';
        }

        // Read stored text only: do not run the_content, render_block or shortcode callbacks.
        $source = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
        $source = preg_replace('/<\/(?:p|div|h[1-6]|li|blockquote)>/i', '$0 ', $source) ?? $source;
        $text = self::text(strip_shortcodes($source));
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return wp_trim_words($text, $words, '…');
    }
}
