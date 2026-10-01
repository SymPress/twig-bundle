<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

/** @internal */
final readonly class TemplateInclude
{
    public function __construct(private TemplateHierarchy $hierarchy, private ThemeRenderer $renderer)
    {
    }

    public function filter(string $php): string
    {
        $real = realpath($php);
        if ($real === false) {
            return $php;
        }
        $relative = null;
        foreach ([get_stylesheet_directory(), get_template_directory()] as $directory) {
            $root = realpath($directory);
            if ($root !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                $relative = substr($real, strlen($root) + 1);
                break;
            }
        }
        if ($relative === null) {
            return $php;
        }
        $candidates = $this->renderer->candidates($this->hierarchy->current());
        $twig = $this->renderer->resolve($candidates);
        if ($twig === null) {
            return $php;
        }
        $phpName = TemplateHierarchy::normalize([$relative])[0] ?? null;
        $twigName = substr($twig, 7, -10);
        $order = array_values(array_unique([...$candidates, 'index']));
        $phpRank = array_search($phpName, $order, true);
        $twigRank = array_search($twigName, $order, true);
        // A filtered theme PHP file not in the native hierarchy is an explicit override.
        if ($phpRank === false || ($twigRank !== false && $phpRank < $twigRank)) {
            return $php;
        }
        $this->renderer->setCurrentCandidates($candidates);
        return dirname(__DIR__, 2) . '/Resources/wordpress/template.php';
    }
}
