<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use Twig\Markup;

/** Public author profile only; no native user or private account data is retained. */
final readonly class User
{
    public int $ID;
    public string $display_name;
    public string $user_nicename;

    public function __construct(\WP_User $user)
    {
        $this->ID = (int) $user->ID;
        $this->display_name = (string) $user->display_name;
        $this->user_nicename = (string) $user->user_nicename;
    }

    public function id(): int
    {
        return $this->ID;
    }

    public function name(): string
    {
        return Html::text($this->display_name);
    }

    public function slug(): string
    {
        return $this->user_nicename;
    }

    public function url(): string
    {
        return esc_url_raw(get_author_posts_url($this->ID, $this->user_nicename));
    }

    public function description(): string
    {
        return Html::text((string) get_the_author_meta('description', $this->ID));
    }

    public function avatar(int $size = 96): Markup
    {
        return Html::trusted((string) get_avatar($this->ID, $size));
    }
}
