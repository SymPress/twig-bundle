<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsTermModel
{
    public function __construct(public string $taxonomy)
    {
    }
}
