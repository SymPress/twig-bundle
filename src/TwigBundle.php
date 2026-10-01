<?php

declare(strict_types=1);

namespace SymPress\TwigBundle;

use SymPress\Framework\SymPressFrameworkBundle;
use SymPress\Kernel\Bundle\AbstractBundle;
use SymPress\TwigBundle\Attribute\AsTwigGlobal;
use SymPress\TwigBundle\DependencyInjection\Compiler\TwigGlobalPass;
use SymPress\TwigBundle\Extension\GlobalProviderInterface;
use Symfony\Bundle\TwigBundle\TwigBundle as SymfonyTwigBundle;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;

#[RequiredBundle(SymPressFrameworkBundle::class, ignoreOnInvalid: true)]
final class TwigBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerExtension(new DependencyInjection\SymPressTwigExtension());
        $container->loadFromExtension('sympress_twig');
        $container->registerForAutoconfiguration(WordPress\TemplateComposerInterface::class)->addTag('sympress.twig.composer');
        $container->registerAttributeForAutoconfiguration(
            WordPress\Attribute\AsTemplateComposer::class,
            static function (ChildDefinition $definition, WordPress\Attribute\AsTemplateComposer $attribute): void {
                $definition->clearTag('sympress.twig.composer')->addTag('sympress.twig.composer', ['templates' => $attribute->templates, 'priority' => $attribute->priority]);
            },
        );
        $container->registerAttributeForAutoconfiguration(
            WordPress\Attribute\AsPostModel::class,
            static function (ChildDefinition $definition, WordPress\Attribute\AsPostModel $attribute): void {
                $definition->setAbstract(true)->addTag('sympress.twig.post_model', ['type' => $attribute->type]);
            },
        );
        $container->addCompilerPass(new DependencyInjection\Compiler\WordPressPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -20);
        $container->registerAttributeForAutoconfiguration(
            WordPress\Attribute\AsTermModel::class,
            static function (ChildDefinition $definition, WordPress\Attribute\AsTermModel $attribute): void {
                $definition->setAbstract(true)->addTag('sympress.twig.term_model', ['type' => $attribute->taxonomy]);
            },
        );

        (new SymfonyTwigBundle())->build($container);

        $container->registerForAutoconfiguration(GlobalProviderInterface::class)
            ->addTag('twig.global_provider');

        $container->registerAttributeForAutoconfiguration(
            AsTwigGlobal::class,
            static function (ChildDefinition $definition, AsTwigGlobal $attribute): void {
                $definition->addTag('twig.global', [
                    'name'     => $attribute->name,
                    'priority' => $attribute->priority,
                ]);
            },
        );

        $container->addCompilerPass(new TwigGlobalPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
    }

    public function boot(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        $activate = function (): void {
            $configuration = $this->container?->get(WordPress\ThemeConfiguration::class);
            if (!$configuration instanceof WordPress\ThemeConfiguration || $configuration->selected() === null) {
                return;
            }

            $custom = $this->container->get(WordPress\CustomTemplates::class);
            if ($custom instanceof WordPress\CustomTemplates) {
                if (did_action('init')) {
                    $custom->register();
                }
                if (!did_action('init')) {
                    add_action('init', $custom->register(...), PHP_INT_MAX);
                }
            }
            if ($configuration->active() === null) {
                return;
            }

            $integration = $this->container->get(WordPress\Integration::class);
            if (!($integration instanceof WordPress\Integration)) {
                return;
            }

            $integration->activate();
        };
        if (did_action('after_setup_theme')) {
            $activate();
            return;
        }
        add_action('after_setup_theme', $activate, PHP_INT_MAX);
    }
}
