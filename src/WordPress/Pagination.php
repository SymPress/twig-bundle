<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final readonly class Pagination
{
    /**
     * @param list<PaginationLink> $pages
     */
    public function __construct(public array $pages = [], public ?PaginationLink $next = null, public ?PaginationLink $prev = null, public int $current = 1)
    {
    }

    /**
     * @param array{total?: int, current?: int, mid_size?: int, end_size?: int, prev_text?: string, next_text?: string, add_args?: array<string, mixed>, add_fragment?: string} $args
     */
    public static function forQuery(?\WP_Query $query, array $args = []): self
    {
        $total = max(0, $args['total'] ?? (int) ($query->max_num_pages ?? 0));
        $current = max(1, $args['current'] ?? (int) ($query?->get('paged') ?: $query?->get('page') ?: 1));
        if ($total < 2) {
            return new self(current: $current);
        }
        $url = static fn (int $page): string => esc_url_raw(add_query_arg($args['add_args'] ?? [], get_pagenum_link($page, false)) . ($args['add_fragment'] ?? ''));
        $pages = [];
        $gap = false;
        for ($page = 1; $page <= $total; ++$page) {
            if ($page <= ($args['end_size'] ?? 1) || $page > $total - ($args['end_size'] ?? 1) || abs($page - $current) <= ($args['mid_size'] ?? 1)) {
                $pages[] = new PaginationLink((string) $page, $url($page), $page === $current);
                $gap = false;
            } elseif (!$gap) {
                $pages[] = new PaginationLink('…', null);
                $gap = true;
            }
        }
        return new self(
            $pages,
            $current < $total ? new PaginationLink($args['next_text'] ?? __('Next'), $url($current + 1)) : null,
            $current > 1 ? new PaginationLink($args['prev_text'] ?? __('Previous'), $url($current - 1)) : null,
            $current,
        );
    }
}
