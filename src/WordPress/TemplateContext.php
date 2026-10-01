<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

final readonly class TemplateContext
{
    /**
     * @param list<string> $candidates
     * @param array<string, mixed> $data
     */
    public function __construct(public string $template, public array $candidates, public array $data)
    {
    }

    public function post(): ?Post
    {
        $post = $this->data['post'] ?? null;
        return $post instanceof Post ? $post : null;
    }
}
