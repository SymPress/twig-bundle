<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final class TemplateHierarchy
{
    private const array TYPES = ['404', 'archive', 'attachment', 'author', 'category', 'date', 'embed', 'frontpage', 'home', 'index', 'page', 'privacypolicy', 'search', 'single', 'singular', 'tag', 'taxonomy'];
    /** @var list<string> */
    private array $captured = [];

    public function register(): void
    {
        foreach (self::TYPES as $type) {
            add_filter($type . '_template_hierarchy', $this->capture(...), PHP_INT_MAX);
        }
    }

    /**
     * @param list<string> $templates
     * @return list<string>
     */
    public function capture(array $templates): array
    {
        $this->captured = self::normalize([...$this->captured, ...$templates]);
        return $templates;
    }

    /**
     * @return list<string>
     */
    public function current(): array
    {
        return self::normalize([...$this->captured, 'index']);
    }

    /**
     * Explicit query resolution never replays template getters or changes globals.
     *
     * @return list<string>
     */
    public function forQuery(\WP_Query $query): array
    {
        $names = [];
        $object = $query->get_queried_object();
        if ($query->is_embed()) {
            if ($object instanceof \WP_Post) {
                $names[] = 'embed-' . $object->post_type . '-' . get_post_format($object);
                $names[] = 'embed-' . $object->post_type;
            }
            $names[] = 'embed';
        }
        foreach (['404' => '404', 'search' => 'search', 'front_page' => 'front-page', 'home' => 'home', 'privacy_policy' => 'privacy-policy'] as $condition => $name) {
            if (!$query->{'is_' . $condition}()) {
                continue;
            }

            $names[] = $name;
        }
        if ($query->is_post_type_archive()) {
            $types = (array) $query->get('post_type');
            $names[] = 'archive-' . ($types[0] ?? 'post');
        }
        if ($object instanceof \WP_Term) {
            $prefix = match ($object->taxonomy) {
                'category' => 'category', 'post_tag' => 'tag', default => 'taxonomy-' . $object->taxonomy
            };
            $names[] = $prefix . '-' . urldecode($object->slug);
            $names[] = $prefix . '-' . $object->slug;
            if ($object->taxonomy === 'category' || $object->taxonomy === 'post_tag') {
                $names[] = $prefix . '-' . $object->term_id;
            }
            $names[] = $prefix;
            if ($query->is_tax()) {
                $names[] = 'taxonomy';
            }
        }
        if ($query->is_singular() && $object instanceof \WP_Post) {
            $custom = get_page_template_slug($object);
            if (is_string($custom) && $custom !== '') {
                $names[] = $custom;
            }
            if ($query->is_attachment()) {
                $mime = explode('/', $object->post_mime_type);
                $names = [...$names, ...$mime, implode('-', $mime), 'attachment'];
            }
            $names = $query->is_page() ? [...$names, 'page-' . urldecode($object->post_name), 'page-' . $object->post_name, 'page-' . $object->ID, 'page'] : [...$names, 'single-' . $object->post_type . '-' . urldecode($object->post_name), 'single-' . $object->post_type . '-' . $object->post_name, 'single-' . $object->post_type, 'single'];
            $names[] = 'singular';
        }
        if ($query->is_author() && $object instanceof \WP_User) {
            $names = [...$names, 'author-' . $object->user_nicename, 'author-' . $object->ID, 'author'];
        }
        if ($query->is_date()) {
            $names[] = 'date';
        }
        if ($query->is_archive()) {
            $names[] = 'archive';
        }
        return self::normalize([...$names, 'index']);
    }

    /**
     * @param array<mixed> $names
     * @return list<string>
     */
    public static function normalize(array $names): array
    {
        $valid = [];
        foreach ($names as $name) {
            // Relative subdirectories are required for custom templates. No traversal or namespaces.
            if (!is_string($name) || !preg_match('~^[\pL\pN_%.-]+(?:/[\pL\pN_%.-]+)*$~u', $name) || str_contains($name, '..')) {
                continue;
            }
            $name = preg_replace('/(?:\.html\.twig|\.php)$/', '', $name);
            if ($name === null || $name === '') {
                continue;
            }

            $valid[] = $name;
        }
        return array_values(array_unique($valid));
    }
}
