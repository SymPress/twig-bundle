<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\TwigBundle\Controller\TwigResponseTrait;
use SymPress\TwigBundle\Renderer\TemplateRendererInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\TemplateWrapper;

final class TwigResponseTraitTest extends TestCase
{
    public function testRendersTemplateResponseThroughInjectedRenderer(): void
    {
        $controller = new class {
            use TwigResponseTrait {
                renderTemplate as public;
                renderView as public;
            }
        };
        $controller->setTemplateRenderer(new class implements TemplateRendererInterface {
            /** @param array<string, mixed> $context */
            public function render(string $template, array $context = []): string
            {
                return sprintf('%s:%s', $template, $context['title'] ?? '');
            }

            public function exists(string $template): bool
            {
                return $template !== '';
            }

            public function load(string $template): TemplateWrapper
            {
                throw new \BadMethodCallException('The test renderer does not load templates.');
            }
        });

        $response = $controller->renderTemplate('page.html.twig', ['title' => 'Hello']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('page.html.twig:Hello', $response->getContent());
    }

    public function testRenderViewFailsWithoutInjectedRenderer(): void
    {
        $controller = new class {
            use TwigResponseTrait {
                renderView as public;
            }
        };

        $this->expectException(\LogicException::class);

        $controller->renderView('page.html.twig');
    }
}
