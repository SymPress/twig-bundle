<?php
declare(strict_types=1);
define('WP_ADMIN', true);
require __DIR__ . '/public/wp/wp-load.php';
$twig = \SymPress\Kernel\App::make(\SymPress\TwigFixture\EarlyRenderer::class)->twig;
if ($twig->createTemplate('{{ __("Admin", "default")|esc_html }}')->render() !== 'Admin') {
    throw new RuntimeException('WordPress helpers are available for admin mail and page rendering.');
}
if (!$twig->getLoader()->exists('@wordpress/document.html.twig') || $twig->getLoader()->exists('@theme/index.html.twig')) {
    throw new RuntimeException('The shared WordPress layout is available in admin; frontend theme paths stay isolated.');
}
$templates = wp_get_theme()->get_page_templates(null, 'page');
if (($templates['custom/landing.html.twig'] ?? null) !== 'Child Landing') {
    throw new RuntimeException('The editor must list the child override for the Twig custom template.');
}
if (has_filter('template_include')) {
    // Only the bundle callback is forbidden here; WordPress/plugins may add others.
    foreach ($GLOBALS['wp_filter']['template_include']->callbacks as $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function']) && $callback['function'][0] instanceof \SymPress\TwigBundle\WordPress\TemplateInclude) {
                throw new RuntimeException('Admin template discovery must not enable frontend interception.');
            }
        }
    }
}
echo "PASS: real admin custom-template discovery and frontend isolation.\n";
