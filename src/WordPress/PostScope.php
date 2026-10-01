<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable -- This adapter owns restoration of WordPress loop globals.

/** Restore every WordPress loop global, including nested renders and exceptions. */
/** @internal */
final class PostScope
{
    private const array GLOBALS = ['post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'wp_query'];

    public static function query(): ?\WP_Query
    {
        $query = $GLOBALS['wp_query'] ?? null;
        return $query instanceof \WP_Query ? $query : null;
    }

    public static function withQuery(\WP_Query $query, callable $callback): mixed
    {
        $saved = self::snapshot();
        try {
            $GLOBALS['wp_query'] = $query;
            return $callback();
        } finally {
            self::restore($saved);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        return array_intersect_key($GLOBALS, array_flip(self::GLOBALS));
    }

    /**
     * @param array<string, mixed> $saved
     */
    public static function restore(array $saved): void
    {
        foreach (self::GLOBALS as $key) {
            if (array_key_exists($key, $saved)) {
                $GLOBALS[$key] = $saved[$key];
                continue;
            }
            unset($GLOBALS[$key]);
        }
    }

    public static function run(\WP_Post $post, callable $callback): mixed
    {
        if (is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            return $callback();
        }
        $saved = self::snapshot();
        $query = $GLOBALS['wp_query'] ?? null;
        $inLoop = $query instanceof \WP_Query ? $query->in_the_loop : false;
        if ($inLoop && ($saved['post'] ?? null) === $post) {
            return $callback();
        }
        try {
            $GLOBALS['post'] = $post;
            if ($query instanceof \WP_Query) {
                $query->in_the_loop = true;
                $query->setup_postdata($post);
            }
            return $callback();
        } finally {
            if ($query instanceof \WP_Query) {
                $query->in_the_loop = $inLoop;
            }
            self::restore($saved);
        }
    }
}
