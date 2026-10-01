<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

/** @internal */
final class Integration
{
    private bool $active = false;

    public function __construct(
        private readonly ThemeConfiguration $configuration,
        private readonly Environment $twig,
        private readonly FilesystemLoader $loader,
        private readonly TemplateHierarchy $hierarchy,
        private readonly TemplateInclude $include,
    ) {
    }

    public function activate(): void
    {
        if ($this->active || $this->configuration->active() === null) {
            return;
        }
        $this->active = true;
        foreach ($this->configuration->paths() as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $this->loader->addPath($path, 'theme');
        }
        $this->loader->addPath(dirname(__DIR__, 2) . '/Resources/views', 'wordpress');
        $escaper = $this->twig->getRuntime(EscaperRuntime::class);
        foreach (['esc_html', 'esc_attr', 'esc_url', 'esc_js', 'wp_kses_post'] as $strategy) {
            $escaper->setEscaper($strategy, static fn (string $value, string $charset): string => $strategy($value));
        }
        $this->hierarchy->register();
        if (!$this->configuration->templateInclude) {
            return;
        }
        add_filter('template_include', $this->include->filter(...), PHP_INT_MAX);
    }
}
