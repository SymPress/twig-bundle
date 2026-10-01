<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsTemplateComposer
{
    /**
     * @param list<string> $templates
     */
    public function __construct(public array $templates = ['*'], public int $priority = 0)
    {
    }
}
