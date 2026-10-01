<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

interface MetaResolverInterface
{
    public function resolve(\WP_Post $post, string $key): mixed;
}
