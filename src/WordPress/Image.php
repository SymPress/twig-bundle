<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use Twig\Markup;

final readonly class Image
{
    public function __construct(public int $id)
    {
    }

    public function url(string $size = 'full'): string
    {
        return esc_url_raw((string) wp_get_attachment_image_url($this->id, $size));
    }

    public function alt(): string
    {
        return (string) get_post_meta($this->id, '_wp_attachment_image_alt', true);
    }

    /**
     * @param array<string, string> $attributes
     */
    public function html(string $size = 'large', array $attributes = []): Markup
    {
        return Html::trusted(wp_get_attachment_image($this->id, $size, false, $attributes));
    }
}
