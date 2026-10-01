<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

/** @internal */
final readonly class CustomTemplates
{
    public function __construct(private ThemeConfiguration $configuration)
    {
    }

    public function register(): void
    {
        foreach (get_post_types() as $type) {
            add_filter('theme_' . $type . '_templates', fn (array $templates): array => $this->templates($templates, $type));
        }
    }

    /**
     * @param array<string, string> $templates
     * @return array<string, string>
     */
    public function templates(array $templates, string $type): array
    {
        $directory = $this->configuration->selected()['page_templates_dir'] ?? 'custom';
        foreach (array_reverse($this->configuration->paths()) as $path) {
            foreach (glob($path . '/' . $directory . '/*.html.twig') ?: [] as $file) {
                $headers = get_file_data($file, ['name' => 'Template Name', 'types' => 'Template Post Type']);
                $types = array_map('trim', explode(',', $headers['types'] ?: 'page'));
                if ($headers['name'] === '' || !in_array($type, $types, true)) {
                    continue;
                }

                $templates[$directory . '/' . basename($file)] = $headers['name'];
            }
        }
        return $templates;
    }
}
