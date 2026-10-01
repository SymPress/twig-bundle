<?php
declare(strict_types=1);
namespace SymPress\TwigFixture;
use SymPress\TwigBundle\WordPress\{Post, MetaResolverInterface, TermFactory};
use SymPress\TwigBundle\WordPress\Attribute\AsPostModel;
#[AsPostModel('post')]
final readonly class InjectedPost extends Post
{
    public function __construct(\WP_Post $post, MetaResolverInterface $meta, TermFactory $terms, private ModelLabel $label)
    {
        parent::__construct($post, $meta, $terms);
    }
    public function label(): string { return $this->label->text(); }
}
