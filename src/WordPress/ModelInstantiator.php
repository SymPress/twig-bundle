<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

/** @internal */
final readonly class ModelInstantiator
{
    /** @param array<string, array<string, array<string, mixed>>> $arguments */
    public function __construct(private array $arguments = [])
    {
    }

    /** @param class-string<Post> $class */
    public function post(string $class, \WP_Post $post, MetaResolverInterface $meta, TermFactory $terms): Post
    {
        if (!isset($this->arguments[$class])) {
            return new $class($post, $meta, $terms);
        }
        return new $class(...$this->values($class, $post, $meta, $terms));
    }

    /** @param class-string<Term> $class */
    public function term(string $class, \WP_Term $term, TermFactory $terms): Term
    {
        if (!isset($this->arguments[$class])) {
            return new $class($term);
        }
        return new $class(...$this->values($class, $term, null, $terms));
    }

    /** @return array<int|string, mixed> */
    private function values(string $class, \WP_Post|\WP_Term $native, ?MetaResolverInterface $meta, TermFactory $terms): array
    {
        $values = [$native];
        foreach ($this->arguments[$class] as $name => $argument) {
            $values[$name] = match ($argument['runtime'] ?? null) {
                'meta' => $meta,
                'terms' => $terms,
                default => isset($argument['service']) ? ($argument['service'])() : $argument['value'],
            };
        }
        return $values;
    }
}
