<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final readonly class TermFactory
{
    /** @param array<string, class-string<Term>> $models */
    public function __construct(private array $models = [])
    {
    }

    public function from(\WP_Term $term): Term
    {
        return new ($this->models[$term->taxonomy] ?? Term::class)($term);
    }
}
