<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Controller;

use SymPress\TwigBundle\Renderer\TemplateRendererInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

trait TwigResponseTrait
{
    private ?TemplateRendererInterface $templateRenderer = null;

    #[Required]
    public function setTemplateRenderer(TemplateRendererInterface $templateRenderer): void
    {
        $this->templateRenderer = $templateRenderer;
    }

    /** @param array<string, mixed> $context */
    protected function renderTemplate(string $template, array $context = [], ?Response $response = null): Response
    {
        $response ??= new Response();
        $response->setContent($this->renderView($template, $context));

        return $response;
    }

    /** @param array<string, mixed> $context */
    protected function renderView(string $template, array $context = []): string
    {
        if (!$this->templateRenderer instanceof TemplateRendererInterface) {
            throw new \LogicException(
                sprintf('The "%s" service was not injected. Is autowiring enabled for this controller?', TemplateRendererInterface::class),
            );
        }

        return $this->templateRenderer->render($template, $context);
    }
}
