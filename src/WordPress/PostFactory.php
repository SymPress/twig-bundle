<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final class PostFactory
{
    /** @var array<string, Post> Keyed by site and post ID: IDs repeat across a multisite network. */
    private array $posts = [];

    /**
     * @param array<string, class-string<Post>> $models
     */
    public function __construct(private readonly MetaResolverInterface $meta, private readonly array $models = [], private readonly TermFactory $terms = new TermFactory(), private readonly ModelInstantiator $instantiator = new ModelInstantiator())
    {
    }

    public function from(\WP_Post $post): Post
    {
        return $this->posts[get_current_blog_id() . ':' . $post->ID] ??= $this->instantiator->post(
            $this->models[$post->post_type] ?? Post::class,
            $post,
            $this->meta,
            $this->terms,
        );
    }
}
