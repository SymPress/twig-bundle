<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use Twig\Extension\AbstractExtension;
use Twig\Extension\AttributeExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/** Registered before Twig initializes, even if a mail/controller renders before theme setup. */
/** @internal */
final class WordPressExtension extends AbstractExtension
{
    public static function create(ThemeConfiguration $configuration): AbstractExtension
    {
        return $configuration->selected() === null ? new InactiveWordPressExtension() : new self($configuration);
    }

    public function __construct(private readonly ThemeConfiguration $configuration)
    {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        $functions = [];
        foreach ($this->extensions() as $extension) {
            $functions = [...$functions, ...$extension->getFunctions()];
        }
        return array_values($functions);
    }

    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        $filters = [];
        foreach ($this->extensions() as $extension) {
            $filters = [...$filters, ...$extension->getFilters()];
        }
        return array_values($filters);
    }

    /** @return list<AttributeExtension> */
    private function extensions(): array
    {
        if ($this->configuration->selected() === null) {
            return [];
        }
        return array_map(static fn (string $runtime): AttributeExtension => new AttributeExtension($runtime), [
            Runtime\TemplateRuntime::class, Runtime\TranslationRuntime::class,
            Runtime\EscapingRuntime::class, Runtime\QueryRuntime::class,
        ]);
    }
}
