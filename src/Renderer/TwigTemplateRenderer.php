<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Renderer;

use Twig\Environment;
use Twig\TemplateWrapper;

final readonly class TwigTemplateRenderer implements TemplateRendererInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    public function exists(string $template): bool
    {
        return $this->twig->getLoader()->exists($template);
    }

    public function load(string $template): TemplateWrapper
    {
        return $this->twig->load($template);
    }
}
