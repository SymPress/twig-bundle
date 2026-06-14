<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsTwigGlobal
{
    public function __construct(
        public string $name,
        public int $priority = 0,
    ) {

        if ($this->name === '') {
            throw new \InvalidArgumentException('Twig global names must be non-empty strings.');
        }
    }
}
