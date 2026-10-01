<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final readonly class MenuItem
{
    /**
     * @param list<string> $classes
     * @param list<MenuItem> $children
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $link,
        public bool $current,
        public array $classes = [],
        public array $children = [],
        public string $target = '',
        public string $rel = '',
    ) {
    }
}
