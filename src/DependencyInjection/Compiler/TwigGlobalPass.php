<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

final class TwigGlobalPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig')) {
            return;
        }

        $twig = $container->getDefinition('twig');
        $globals = [];

        foreach ($container->findTaggedServiceIds('twig.global', true) as $id => $tags) {
            foreach ($tags as $tag) {
                $name = $tag['name'] ?? null;

                if (!is_string($name) || $name === '') {
                    throw new InvalidArgumentException(
                        sprintf('Service "%s" uses the "twig.global" tag without a non-empty "name" attribute.', $id),
                    );
                }

                $priority = is_numeric($tag['priority'] ?? null) ? (int) $tag['priority'] : 0;
                $globals[] = [$priority, $name, $id];
            }
        }

        usort(
            $globals,
            static fn (array $left, array $right): int => $left[0] <=> $right[0],
        );

        foreach ($globals as [, $name, $id]) {
            $twig->addMethodCall('addGlobal', [$name, new Reference($id)]);
        }
    }
}
