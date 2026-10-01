<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final readonly class Menu
{
    /**
     * @param list<MenuItem> $items
     */
    public function __construct(public array $items = [])
    {
    }

    /**
     * @param array<string, mixed> $args Native wp_nav_menu arguments; object collection owns the walker.
     */
    public static function at(string $location, array $args = []): self
    {
        $id = get_nav_menu_locations()[$location] ?? 0;
        if (!$id) {
            return new self();
        }
        $menu = wp_get_nav_menu_object($id);
        if (!$menu) {
            return new self();
        }
        $walker = new MenuWalker();
        wp_nav_menu([
        ...$args, 'menu' => $menu,
        'theme_location' => $location,
            'echo'       => false,
        'fallback_cb'    => false,
        'container'      => false,
            'items_wrap' => '%3$s',
        'walker'         => $walker,
        ]);
        return new self($walker->getItems());
    }
}
