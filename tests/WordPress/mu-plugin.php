<?php
declare(strict_types=1);
if (!wp_installing()) {
    $wrong = static function (string $function): void {
        if ($function === 'wp_is_block_theme') { throw new \RuntimeException('Block-theme inspection ran during early bootstrap.'); }
    };
    add_action('doing_it_wrong_run', $wrong);
    \SymPress\Kernel\App::bootKernel(new \SymPress\Kernel\Kernel\SiteKernel(__DIR__, 'test', true));
    if (\SymPress\Kernel\App::make(\SymPress\TwigBundle\WordPress\ThemeConfiguration::class)->selected() !== null) {
        $twig = \SymPress\Kernel\App::make(\SymPress\TwigFixture\EarlyRenderer::class)->twig;
        if ($twig->createTemplate('{{ __("Early", "default") }}')->render() !== 'Early') {
            throw new \RuntimeException('WordPress helpers must be available during MU-plugin bootstrap.');
        }
    }
    remove_action('doing_it_wrong_run', $wrong);
}
