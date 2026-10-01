<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use Twig\Markup;

readonly class Post
{
    private \stdClass $cache;

    public function __construct(public \WP_Post $wpPost, private MetaResolverInterface $metaResolver, private TermFactory $terms = new TermFactory())
    {
        $this->cache = new \stdClass();
    }

    public function id(): int
    {
        return $this->wpPost->ID;
    }

    public function title(): string
    {
        return Html::text(get_the_title($this->wpPost));
    }

    public function url(): string
    {
        return esc_url_raw((string) get_permalink($this->wpPost));
    }

    public function type(): string
    {
        return $this->wpPost->post_type;
    }

    public function protected(): bool
    {
        return post_password_required($this->wpPost);
    }

    public function date(?string $format = null): string
    {
        return (string) get_the_date($format ?? '', $this->wpPost);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- Public Twig attribute name.
    public function date_iso(): string
    {
        return $this->date(DATE_W3C);
    }

    public function content(): Markup
    {
        if (!isset($this->cache->content)) {
            $this->cache->content = PostScope::run($this->wpPost, function (): Markup {
                return Html::trusted($this->protected() ? get_the_password_form($this->wpPost)
                    : str_replace(']]>', ']]&gt;', (string) apply_filters('the_content', get_the_content(null, false, $this->wpPost))));
            });
        }
        return $this->cache->content;
    }

    public function excerpt(int $words = 30): string
    {
        $key = 'excerpt_' . $words;
        return $this->cache->{$key} ??= PostScope::run($this->wpPost, fn (): string => Excerpt::fromPost($this->wpPost, $words));
    }

    /**
     * @param string|array{int, int} $size
     * @param array<string, string> $attributes
     */
    public function thumbnail(string|array $size = 'post-thumbnail', array $attributes = []): Markup
    {
        return $this->cache->{'thumbnail_' . md5(serialize([$size, $attributes]))} ??= Html::trusted($this->protected() ? '' : get_the_post_thumbnail($this->wpPost, $size, $attributes));
    }

    /**
     * @return list<Term>
     */
    public function categories(): array
    {
        return $this->protected() ? [] : array_map($this->terms->from(...), array_values(get_the_category($this->id())));
    }

    public function meta(string $key): mixed
    {
        // Protected content must not leak through template metadata.
        return $this->protected() ? null : $this->metaResolver->resolve($this->wpPost, $key);
    }
}
