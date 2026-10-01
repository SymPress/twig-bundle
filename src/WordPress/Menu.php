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
     * @param array{depth?: int} $args
     */
    public static function at(string $location, array $args = []): self
    {
        $id = get_nav_menu_locations()[$location] ?? 0;
        if (!$id) {
            return new self();
        }
        $items = wp_get_nav_menu_items($id) ?: [];
        _wp_menu_item_classes_by_context($items);
        $items = apply_filters('wp_nav_menu_objects', $items, (object) ['theme_location' => $location, 'menu' => $id, 'depth' => $args['depth'] ?? 0]);
        return new self(self::children($items, 0, (int) ($args['depth'] ?? 0), []));
    }

    /**
     * @param array<\WP_Post&object{menu_item_parent: int|string, title: string, url: string, current: bool, classes: list<string>, target: string, xfn: string}> $items
     * @param list<int> $seen
     * @return list<MenuItem>
     */
    private static function children(array $items, int $parent, int $depth, array $seen): array
    {
        $children = [];
        foreach ($items as $item) {
            if ((int) $item->menu_item_parent !== $parent || in_array($item->ID, $seen, true)) {
                continue;
            }
            $children[] = new MenuItem(
                $item->ID,
                Html::text($item->title),
                esc_url_raw($item->url),
                (bool) $item->current,
                array_values(array_filter($item->classes)),
                $depth === 1 ? [] : self::children($items, $item->ID, max(0, $depth - 1), [...$seen, $item->ID]),
                $item->target,
                $item->xfn,
            );
        }
        return $children;
    }
}
