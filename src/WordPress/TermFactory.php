<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final readonly class TermFactory
{
    /** @param array<string, class-string<Term>> $models */
    public function __construct(private array $models = [], private ModelInstantiator $instantiator = new ModelInstantiator())
    {
    }

    public function from(\WP_Term $term): Term
    {
        return $this->instantiator->term($this->models[$term->taxonomy] ?? Term::class, $term, $this);
    }
}
