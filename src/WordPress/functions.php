<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use SymPress\Kernel\App;

/**
 * @param string|list<string> $template
 * @param array<string, mixed> $context
 */
function render(string|array $template, array $context = []): string
{
    return App::make(ThemeRenderer::class)->render($template, $context);
}
