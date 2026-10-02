<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

/**
     * @implements \IteratorAggregate<int, Post>
     */
final readonly class PostCollection implements \IteratorAggregate, \Countable
{
    public function __construct(private \WP_Query $query, private PostFactory $factory)
    {
    }

    public function count(): int
    {
        return max(0, $this->query->post_count);
    }

    /**
     * @return \Traversable<int, Post>
     */
    public function getIterator(): \Traversable // phpcs:ignore SymPress.Functions.ReturnTypeDeclaration.IncorrectVoidReturn -- Generator termination, not a void method.
    {
        if (is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            foreach ($this->query->posts ?? [] as $post) {
                $post = get_post($post);
                if (!($post instanceof \WP_Post)) {
                    continue;
                }

                yield $this->factory->from($post);
            }
            return;
        }
        $saved = PostScope::snapshot();
        $state = [$this->query->current_post, $this->query->in_the_loop, $this->query->before_loop, $this->query->post ?? null];
        $ended = false;
        try {
            // Native secondary loops replace post globals, not the main query.
            if ($this->query === ($GLOBALS['wp_the_query'] ?? null)) { // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable -- Native main-query identity.
                $GLOBALS['wp_query'] = $this->query; // phpcs:ignore SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable -- Restore the native main query.
            }
            $this->query->rewind_posts();
            while ($this->query->have_posts()) {
                $this->query->the_post();
                $post = $this->query->post;
                if (!($post instanceof \WP_Post)) {
                    continue;
                }

                yield $this->factory->from($post);
            }
            $ended = true;
        } finally {
            if (!$ended && $this->query->in_the_loop) {
                $query = $this->query;
                do_action_ref_array('loop_end', [&$query]);
            }
            wp_reset_postdata();
            [$this->query->current_post, $this->query->in_the_loop, $this->query->before_loop, $this->query->post] = $state;
            PostScope::restore($saved);
        }
    }
}
