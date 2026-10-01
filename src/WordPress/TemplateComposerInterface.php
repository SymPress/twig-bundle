<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

interface TemplateComposerInterface
{
    /**
     * @return array<string, mixed>
     */
    public function compose(TemplateContext $context): array;
}
