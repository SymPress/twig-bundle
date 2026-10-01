<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\DependencyInjection\Compiler;

use SymPress\TwigBundle\WordPress\MetaResolverInterface;
use SymPress\TwigBundle\WordPress\TermFactory;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/** @internal */
final class ModelArguments
{
    /**
     * @param class-string $class
     * @return array<string, array<string, mixed>>
     */
    public function collect(ContainerBuilder $container, string $id, string $class, bool $post): array
    {
        $definition = $container->getDefinition($id);
        $parameters = (new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [];
        if ($parameters === []) {
            throw new \InvalidArgumentException(
                'Models must accept the native WordPress object as their first argument.',
            );
        }
        $arguments = [];
        foreach (array_slice($parameters, 1) as $parameter) {
            if ($parameter->isVariadic()) {
                continue;
            }
            $argument = $this->resolve($container, $definition, $parameter, $post);
            if ($argument === null) {
                continue;
            }
            $arguments[$parameter->getName()] = $argument;
        }
        return $arguments;
    }

    /** @return array<string, mixed>|null */
    private function resolve(
        ContainerBuilder $container,
        Definition $definition,
        \ReflectionParameter $parameter,
        bool $post,
    ): ?array {

        $name = '$' . $parameter->getName();
        $arguments = $definition->getArguments();
        foreach ([$name, $parameter->getPosition()] as $key) {
            if (array_key_exists($key, $arguments)) {
                return $this->value($arguments[$key]);
            }
        }
        $type = $parameter->getType();
        $class = $type instanceof \ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
        foreach (array_filter([$class === null ? null : $class . ' ' . $name, $name, $class]) as $binding) {
            if (isset($definition->getBindings()[$binding])) {
                return $this->value($definition->getBindings()[$binding]->getValues()[0]);
            }
        }
        if ($post && $class === MetaResolverInterface::class) {
            return ['runtime' => 'meta'];
        }
        if ($class === TermFactory::class) {
            return ['runtime' => 'terms'];
        }
        if ($class !== null && $container->has($class)) {
            return ['service' => new ServiceClosureArgument(new Reference($class))];
        }
        if ($parameter->isDefaultValueAvailable()) {
            return null;
        }
        if ($parameter->allowsNull()) {
            return ['value' => null];
        }
        throw new \InvalidArgumentException(
            'Cannot inject model argument ' . $name . ': register its type or bind it explicitly.',
        );
    }

    /** @return array<string, mixed> */
    private function value(mixed $value): array
    {
        return $value instanceof Reference
            ? ['service' => new ServiceClosureArgument($value)]
            : ['value' => $value];
    }
}
