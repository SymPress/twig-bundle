<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final readonly class PaginationLink
{
    public function __construct(public string $title, public ?string $url, public bool $current = false)
    {
    }
}
