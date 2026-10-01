<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Fixtures;

final readonly class ModelLabel
{
    public function text(): string
    {
        return 'injected';
    }
}
