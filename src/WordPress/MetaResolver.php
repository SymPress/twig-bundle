<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

/** ACF is optional and detected at access time, after plugins have loaded. */
final class MetaResolver implements MetaResolverInterface
{
    /** @param (\Closure(): PostFactory)|null $postFactory */
    public function __construct(private readonly ?\Closure $postFactory = null)
    {
    }

    public function resolve(\WP_Post $post, string $key): mixed
    {
        if (!function_exists('get_field_object')) {
            return get_post_meta($post->ID, $key, true);
        }
        $field = get_field_object($key, $post->ID);
        if (!is_array($field)) {
            return get_post_meta($post->ID, $key, true);
        }
        return $this->convert($field);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function convert(array $field): mixed
    {
        $value = $field['value'] ?? null;
        if ($value === null || $value === false || $value === '') {
            return $value;
        }
        return match ($field['type'] ?? '') {
            'image' => $this->image($value),
            'gallery' => array_map($this->image(...), (array) $value),
            'post_object', 'relationship' => is_array($value) ? array_map($this->post(...), $value) : $this->post($value),
            'date_picker', 'date_time_picker' => $this->date($value, (string) ($field['return_format'] ?? 'Ymd')),
            default => $value,
        };
    }

    private function image(mixed $value): ?Image
    {
        $id = is_array($value) ? ($value['ID'] ?? $value['id'] ?? 0) : (is_numeric($value) ? (int) $value : attachment_url_to_postid((string) $value));
        return $id > 0 ? new Image((int) $id) : null;
    }

    private function post(mixed $value): ?Post
    {
        if (!$value) {
            return null;
        }
        $post = get_post($value);
        if (!$post instanceof \WP_Post) {
            return null;
        }
        return $this->postFactory !== null ? ($this->postFactory)()->from($post) : new Post($post, $this);
    }

    private function date(mixed $value, string $format): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, (string) $value, wp_timezone());
        $errors = \DateTimeImmutable::getLastErrors();
        return $date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ? null : $date;
    }
}
