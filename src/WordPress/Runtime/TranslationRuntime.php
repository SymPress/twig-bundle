<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress\Runtime;

use SymPress\TwigBundle\WordPress\ThemeConfiguration;
use Twig\Attribute\AsTwigFunction;

/** @internal */
final readonly class TranslationRuntime
{
    public function __construct(private ThemeConfiguration $configuration)
    {
    }

    #[AsTwigFunction('__')]
    public function translate(string $text, ?string $domain = null): string
    {
        return __($text, $domain ?? $this->configuration->textDomain());
    }

    #[AsTwigFunction('_x')]
    public function context(string $text, string $context, ?string $domain = null): string
    {
        return _x($text, $context, $domain ?? $this->configuration->textDomain());
    }

    #[AsTwigFunction('_n')]
    public function plural(string $single, string $plural, int $number, ?string $domain = null): string
    {
        return _n($single, $plural, $number, $domain ?? $this->configuration->textDomain());
    }

    #[AsTwigFunction('_nx')]
    public function pluralContext(string $single, string $plural, int $number, string $context, ?string $domain = null): string
    {
        return _nx($single, $plural, $number, $context, $domain ?? $this->configuration->textDomain());
    }
}
