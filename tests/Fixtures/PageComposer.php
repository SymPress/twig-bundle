<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Fixtures;

use SymPress\TwigBundle\WordPress\Attribute\AsTemplateComposer;
use SymPress\TwigBundle\WordPress\TemplateComposerInterface;
use SymPress\TwigBundle\WordPress\TemplateContext;

#[AsTemplateComposer(['page-*'], 42)]
final class PageComposer implements TemplateComposerInterface
{
    public function compose(TemplateContext $context): array
    {
        return ['composed' => true];
    }
}
