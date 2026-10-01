<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Fixtures;

use SymPress\TwigBundle\WordPress\Attribute\AsPostModel;
use SymPress\TwigBundle\WordPress\MetaResolverInterface;
use SymPress\TwigBundle\WordPress\Post;
use SymPress\TwigBundle\WordPress\TermFactory;

#[AsPostModel('injected')]
final readonly class InjectedPost extends Post
{
    public function __construct(\WP_Post $post, MetaResolverInterface $meta, TermFactory $terms, private ModelLabel $label, private string $prefix = 'default')
    {
        parent::__construct($post, $meta, $terms);
    }

    public function label(): string
    {
        return $this->prefix . ' ' . $this->label->text();
    }
}
