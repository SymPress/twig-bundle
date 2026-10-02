<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use Twig\Environment;

final class ThemeRenderer
{
    private ?string $lastResolved = null;
    /** @var list<string>|null */
    private ?array $currentCandidates = null;

    /**
     * @param iterable<array{composer: TemplateComposerInterface, templates: list<string>, priority: int}> $composers
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly TemplateHierarchy $hierarchy,
        private readonly QueryContextProvider $context,
        private readonly ThemeConfiguration $configuration,
        private readonly iterable $composers = [],
    ) {
    }

    public function renderCurrent(): string
    {
        return $this->renderCandidates($this->currentCandidates ?? $this->candidates($this->hierarchy->current()), [], true);
    }

    /** @param list<string> $candidates */
    public function setCurrentCandidates(array $candidates): void
    {
        $this->currentCandidates = $candidates;
    }

    public function resolved(): ?string
    {
        return $this->lastResolved;
    }

    /**
     * @param list<string> $candidates
     */
    public function resolve(array $candidates): ?string
    {
        foreach (TemplateHierarchy::normalize([...$candidates, 'index']) as $candidate) {
            $name = '@theme/' . $candidate . '.html.twig';
            if ($this->twig->getLoader()->exists($name)) {
                return $name;
            }
        }
        return null;
    }

    /**
     * @param list<string> $candidates
     * @return list<string>
     */
    public function candidates(array $candidates): array
    {
        $filtered = apply_filters($this->configuration->hookPrefix . '/template_candidates', $candidates);
        return TemplateHierarchy::normalize(is_array($filtered) ? $filtered : $candidates);
    }

    /**
     * @param string|list<string> $candidates
     * @param array<string, mixed> $context
     */
    public function render(string|array $candidates, array $context = []): string
    {
        return $this->renderCandidates($this->candidates((array) $candidates), $context);
    }

    /**
     * @param list<string> $candidates
     * @param array<string, mixed> $context
     */
    private function renderCandidates(array $candidates, array $context, bool $current = false): string
    {
        $template = $this->resolve($candidates) ?? throw new \RuntimeException('No @theme template found, including index.html.twig.');
        $this->lastResolved = $template;
        $html = $this->twig->render($template, $this->compose($template, $candidates, $context));
        if ($current && $this->configuration->debugComment) {
            $debug = str_replace(['--', '<', '>'], ['- -', '&lt;', '&gt;'], $template . ' [' . implode(', ', $candidates) . ']');
            $html .= "\n<!-- twig: " . $debug . ' -->';
        }
        return $html;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderBlock(string $template, string $block, array $context = []): string
    {
        $candidates = $this->candidates([$template]);
        $resolved = $this->resolve($candidates) ?? throw new \RuntimeException('No @theme template found.');
        $this->lastResolved = $resolved;
        return $this->twig->load($resolved)->renderBlock($block, $this->compose($resolved, $candidates, $context));
    }

    /**
     * @param list<string> $candidates
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function compose(string $template, array $candidates, array $context): array
    {
        $data = array_replace($this->context->context(), $context);
        $composers = is_array($this->composers) ? $this->composers : iterator_to_array($this->composers);
        usort($composers, static fn (array $left, array $right): int => $left['priority'] <=> $right['priority']);
        foreach ($composers as $entry) {
            $matches = array_any($entry['templates'], static fn (string $pattern): bool => array_any($candidates, static fn (string $candidate): bool => fnmatch($pattern, $candidate)));
            if (!$matches) {
                continue;
            }

            $data = array_replace($data, $entry['composer']->compose(new TemplateContext($template, $candidates, $data)));
        }
        $filtered = apply_filters($this->configuration->hookPrefix . '/context', $data, $template);
        return is_array($filtered) ? $filtered : $data;
    }
}
