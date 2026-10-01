<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress\Runtime;

use Twig\Attribute\AsTwigFunction;

/** @internal */
final class QueryRuntime
{
    #[AsTwigFunction('is_front_page')]
    public function frontPage(): bool
    {
        return is_front_page();
    }

    #[AsTwigFunction('is_home')]
    public function home(): bool
    {
        return is_home();
    }

    /**
     * @param string|list<string> $types
     */
    #[AsTwigFunction('is_singular')]
    public function singular(string|array $types = ''): bool
    {
        return is_singular($types);
    }

    #[AsTwigFunction('is_archive')]
    public function archive(): bool
    {
        return is_archive();
    }

    #[AsTwigFunction('has_nav_menu')]
    public function hasMenu(string $location): bool
    {
        return has_nav_menu($location);
    }
}
