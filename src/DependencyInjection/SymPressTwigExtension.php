<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\DependencyInjection;

use SymPress\TwigBundle\WordPress;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Twig\Extension\AbstractExtension;

final class SymPressTwigExtension extends Extension
{
    public function getAlias(): string
    {
        return 'sympress_twig';
    }

    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $tree = new TreeBuilder('sympress_twig');
        $tree->getRootNode()->children()->arrayNode('wordpress')->addDefaultsIfNotSet()->children()
            ->scalarNode('hook_prefix')->defaultValue('sympress/twig')->cannotBeEmpty()->end()
            ->booleanNode('template_include')->defaultTrue()->end()
            ->booleanNode('debug_comment')->defaultValue((bool) $container->getParameter('kernel.debug'))->end()
            ->arrayNode('themes')->normalizeKeys(false)->useAttributeAsKey('slug')->arrayPrototype()->addDefaultsIfNotSet()->children()
                ->scalarNode('views_dir')->defaultValue('resources/views')->validate()->ifTrue(static fn (mixed $value): bool => !is_string($value) || WordPress\TemplateHierarchy::normalize([$value]) !== [$value])->thenInvalid('views_dir must be a safe relative directory.')->end()->end()
                ->scalarNode('page_templates_dir')->defaultValue('custom')->validate()->ifTrue(static fn (mixed $value): bool => !is_string($value) || WordPress\TemplateHierarchy::normalize([$value]) !== [$value])->thenInvalid('page_templates_dir must be a safe relative directory.')->end()->end()
                ->scalarNode('text_domain')->defaultNull()->end()
            ->end()->end()->end()
        ->end()->end()->end();
        $config = (new Processor())->process($tree->buildTree(), $configs)['wordpress'];
        $container->register(WordPress\ThemeConfiguration::class, WordPress\ThemeConfiguration::class)->setArguments([$config['themes'], $config['hook_prefix'], $config['template_include'], $config['debug_comment']])->setPublic(true);
        foreach ([WordPress\TemplateHierarchy::class, WordPress\PostFactory::class, WordPress\MetaResolver::class, WordPress\QueryContextProvider::class, WordPress\CustomTemplates::class, WordPress\TemplateInclude::class, WordPress\ThemeRenderer::class, WordPress\Integration::class] as $class) {
            $container->register($class)->setAutowired(true)->setPublic(true);
        }
        $container->setAlias(WordPress\MetaResolverInterface::class, WordPress\MetaResolver::class);
        $container->register(WordPress\TermFactory::class, WordPress\TermFactory::class);
        $container->getDefinition(WordPress\MetaResolver::class)->setArgument('$postFactory', new ServiceClosureArgument(new Reference(WordPress\PostFactory::class)));
        $container->getDefinition(WordPress\Integration::class)->setArgument('$loader', new Reference('twig.loader.native_filesystem'));
        foreach ([WordPress\Runtime\TemplateRuntime::class, WordPress\Runtime\TranslationRuntime::class, WordPress\Runtime\EscapingRuntime::class, WordPress\Runtime\QueryRuntime::class] as $class) {
            $container->register($class)->setAutowired(true)->addTag('twig.runtime');
        }
        // A registered theme exposes a lazy Site object, including for mail/controller rendering.
        if ($config['themes'] === []) {
            return;
        }

        $container->register(WordPress\Site::class)->setAutoconfigured(true);
        $container->register('sympress.twig.wordpress_extension', AbstractExtension::class)
            ->setFactory([WordPress\WordPressExtension::class, 'create'])
            ->setArguments([new Reference(WordPress\ThemeConfiguration::class)])
            ->addTag('twig.extension');
    }
}
