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
}
