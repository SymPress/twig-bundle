<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Fixtures;

use SymPress\TwigBundle\WordPress\Attribute\AsTermModel;
use SymPress\TwigBundle\WordPress\Term;

#[AsTermModel('category')]
final readonly class InjectedTerm extends Term
{
    public function __construct(\WP_Term $term, private ModelLabel $label)
    {
        parent::__construct($term);
    }

    public function label(): string
    {
        return $this->label->text();
    }
}
