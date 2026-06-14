<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Renderer;

use Twig\TemplateWrapper;

interface TemplateRendererInterface
{
    /** @param array<string, mixed> $context */
    public function render(string $template, array $context = []): string;

    public function exists(string $template): bool;

    public function load(string $template): TemplateWrapper;
}
