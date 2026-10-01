<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use SymPress\TwigBundle\Attribute\AsTwigGlobal;
use Twig\Markup;

#[AsTwigGlobal('site')]
final class Site
{
    public function name(): string
    {
        return Html::text(get_bloginfo('name'));
    }

    public function description(): string
    {
        return Html::text(get_bloginfo('description'));
    }

    public function url(): string
    {
        return esc_url_raw(home_url('/'));
    }

    public function charset(): string
    {
        return get_bloginfo('charset');
    }

    public function logo(): Markup
    {
        return Html::trusted(get_custom_logo());
    }

    public function language(): string
    {
        return get_bloginfo('language');
    }
}
