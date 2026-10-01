<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

/** @internal */
final readonly class ThemeConfiguration
{
    /**
     * @param array<string, array{views_dir?: string, page_templates_dir?: string, text_domain?: ?string}> $themes
     */
    public function __construct(
        public array $themes = [],
        public string $hookPrefix = 'sympress/twig',
        public bool $templateInclude = true,
        public bool $debugComment = false,
    ) {
    }

    /**
     * @return array{views_dir: string, page_templates_dir: string, text_domain: ?string}|null
     */
    public function active(): ?array
    {
        if (!function_exists('get_stylesheet') || (is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax()))) {
            return null;
        }
        return $this->selected();
    }

    /**
     * Resolve theme registration in both the editor and frontend.
     *
     * @return array{views_dir: string, page_templates_dir: string, text_domain: ?string}|null
     */
    public function selected(): ?array
    {
        if (!function_exists('get_stylesheet')) {
            return null;
        }
        $config = $this->themes[get_stylesheet()] ?? $this->themes[get_template()] ?? null;
        // Theme roots are not registered during MU-plugin boot.
        if ($config !== null && did_action('after_setup_theme') && wp_is_block_theme()) {
            return null;
        }
        return $config === null ? null : array_replace([
            'views_dir' => 'resources/views',
            'page_templates_dir' => 'custom',
            'text_domain' => null,
        ], $config);
    }

    public function textDomain(): string
    {
        return $this->selected()['text_domain'] ?? (function_exists('wp_get_theme') ? (string) wp_get_theme()->get('TextDomain') : 'default');
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        $config = $this->selected();
        if ($config === null) {
            return [];
        }
        return array_values(array_unique([
            get_stylesheet_directory() . '/' . $config['views_dir'],
            get_template_directory() . '/' . $config['views_dir'],
        ]));
    }
}
