<?php

declare(strict_types=1);

use SymPress\Kernel\App;
use SymPress\TwigBundle\WordPress\ThemeRenderer;

if (!defined('ABSPATH')) {
    exit;
}

// Twig escapes template data; WordPress HTML crosses the explicit Html boundary.
echo App::make(ThemeRenderer::class)->renderCurrent(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
