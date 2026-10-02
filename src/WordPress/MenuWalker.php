<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

/** @internal Collect objects after WordPress has run its native menu preparation and filters. */
final class MenuWalker extends \Walker_Nav_Menu
{
    /** @var list<MenuItem> */
    private array $items = [];

    public function walk(mixed $elements, mixed $max_depth, mixed ...$args): string
    {
        $this->items = $this->children($elements ?? [], 0, (int) $max_depth, $args[0], 0, []);
        return '';
    }

    /** @return list<MenuItem> */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array<\WP_Post&object{menu_item_parent: int|string, title: string, url: string, current: bool, classes: list<string>, target: string, xfn: string, description: string, attr_title: string}> $items
     * @param list<int> $seen
     * @return list<MenuItem>
     */
    private function children(array $items, int $parent, int $depth, object $args, int $level, array $seen): array
    {
        $children = [];
        foreach ($items as $item) {
            if ((int) $item->menu_item_parent !== $parent || in_array($item->ID, $seen, true)) {
                continue;
            }
            $itemArgs = apply_filters('nav_menu_item_args', $args, $item, $level);
            $title = apply_filters('the_title', $item->title, $item->ID);
            $title = apply_filters('nav_menu_item_title', $title, $item, $itemArgs, $level);
            $classes = apply_filters('nav_menu_css_class', array_filter($item->classes), $item, $itemArgs, $level);
            $children[] = new MenuItem(
                $item->ID,
                Html::text($title),
                esc_url_raw($item->url),
                (bool) $item->current,
                array_values($classes),
                $depth === 1 ? [] : $this->children($items, $item->ID, max(0, $depth - 1), $args, $level + 1, [...$seen, $item->ID]),
                $item->target,
                $item->xfn,
                Html::text($item->description),
                Html::text($item->attr_title),
            );
        }
        return $children;
    }
}
