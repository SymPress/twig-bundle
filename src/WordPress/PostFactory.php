<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final class PostFactory
{
    /** @var array<int, Post> */
    private array $posts = [];

    /**
     * @param array<string, class-string<Post>> $models
     */
    public function __construct(private readonly MetaResolverInterface $meta, private readonly array $models = [], private readonly TermFactory $terms = new TermFactory())
    {
    }

    public function from(\WP_Post $post): Post
    {
        return $this->posts[$post->ID] ??= new ($this->models[$post->post_type] ?? Post::class)($post, $this->meta, $this->terms);
    }
}
