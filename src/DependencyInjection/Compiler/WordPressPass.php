<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\DependencyInjection\Compiler;

use SymPress\TwigBundle\WordPress\Post;
use SymPress\TwigBundle\WordPress\PostFactory;
use SymPress\TwigBundle\WordPress\TemplateComposerInterface;
use SymPress\TwigBundle\WordPress\ThemeRenderer;
use SymPress\TwigBundle\WordPress\Term;
use SymPress\TwigBundle\WordPress\TermFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class WordPressPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ThemeRenderer::class)) {
            return;
        }
        $composers = [];
        foreach ($container->findTaggedServiceIds('sympress.twig.composer') as $id => $tags) {
            $class = $container->getDefinition($id)->getClass();
            if (!is_string($class) || !is_a($class, TemplateComposerInterface::class, true)) {
                throw new \InvalidArgumentException('Template composers must implement TemplateComposerInterface.');
            }
            $tag = array_find($tags, static fn (array $tag): bool => isset($tag['templates'])) ?? [];
            $composers[] = ['composer' => new Reference($id), 'templates' => $tag['templates'] ?? ['*'], 'priority' => $tag['priority'] ?? 0];
        }
        $container->getDefinition(ThemeRenderer::class)->setArgument('$composers', $composers);
        $models = [];
        foreach ($container->findTaggedServiceIds('sympress.twig.post_model') as $id => $tags) {
            $class = $container->getDefinition($id)->getClass();
            if (!is_string($class) || !is_subclass_of($class, Post::class)) {
                throw new \InvalidArgumentException('Post models must extend Post.');
            }
            foreach ($tags as $tag) {
                $type = $tag['type'];
                if (isset($models[$type])) {
                    throw new \InvalidArgumentException('Duplicate post model for ' . $type);
                }
                $models[$type] = $class;
            }
        }
        $container->getDefinition(PostFactory::class)->setArgument('$models', $models);
        $terms = [];
        foreach ($container->findTaggedServiceIds('sympress.twig.term_model') as $id => $tags) {
            $class = $container->getDefinition($id)->getClass();
            if (!is_string($class) || !is_subclass_of($class, Term::class)) {
                throw new \InvalidArgumentException('Term models must extend Term.');
            }
            foreach ($tags as $tag) {
                if (isset($terms[$tag['type']])) {
                    throw new \InvalidArgumentException('Duplicate term model for ' . $tag['type']);
                }
                $terms[$tag['type']] = $class;
            }
        }
        $container->getDefinition(TermFactory::class)->setArgument('$models', $terms);
    }
}
