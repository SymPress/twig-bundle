<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

readonly class Term
{
    public function __construct(public \WP_Term $wpTerm)
    {
    }

    public function id(): int
    {
        return $this->wpTerm->term_id;
    }

    public function name(): string
    {
        return Html::text($this->wpTerm->name);
    }

    public function slug(): string
    {
        return $this->wpTerm->slug;
    }

    public function taxonomy(): string
    {
        return $this->wpTerm->taxonomy;
    }

    public function url(): string
    {
        $url = get_term_link($this->wpTerm);
        return is_wp_error($url) ? '' : esc_url_raw($url);
    }
}
