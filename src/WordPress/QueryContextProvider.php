<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final class QueryContextProvider
{
    /** @var \WeakMap<\WP_Query, array<string, mixed>> */
    private \WeakMap $contexts;

    public function __construct(private readonly PostFactory $posts, private readonly TermFactory $terms = new TermFactory())
    {
        $this->contexts = new \WeakMap();
    }

    /**
     * @return array<string, mixed>
     */
    public function context(?\WP_Query $query = null): array
    {
        $query ??= PostScope::query();
        if (!$query instanceof \WP_Query) {
            return [];
        }
        if (isset($this->contexts[$query])) {
            return $this->contexts[$query];
        }
        if ($query !== PostScope::query()) {
            return PostScope::withQuery($query, fn (): array => $this->context($query));
        }
        $object = $query->get_queried_object();
        $post = $query->is_singular() && $object instanceof \WP_Post ? $this->posts->from($object) : null;
        $this->contexts[$query] = [
            'posts' => new PostCollection($query, $this->posts),
            'post' => $post,
            'term' => $object instanceof \WP_Term ? $this->terms->from($object) : null,
            'author' => $object instanceof \WP_User ? new User($object) : null,
            'title' => Html::text($query->is_archive() ? get_the_archive_title() : ($post?->title() ?? (get_option('page_for_posts') ? get_the_title((int) get_option('page_for_posts')) : get_bloginfo('name')))),
            'description' => Html::trusted($query->is_archive() ? get_the_archive_description() : ''),
            'search_query' => (string) $query->get('s'),
            'show_comments' => $post !== null && !$post->protected() && (comments_open($post->id()) || get_comments_number($post->id())),
        ];
        return $this->contexts[$query];
    }
}
