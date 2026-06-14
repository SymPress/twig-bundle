<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\DependencyInjection;

use Symfony\Bundle\TwigBundle\DependencyInjection\Configuration as SymfonyTwigConfiguration;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension as SymfonyTwigExtension;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TwigExtension extends SymfonyTwigExtension
{
    /** @param array<int, array<string, mixed>> $config */
    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new SymfonyTwigConfiguration();
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->initializeKernelParameters($container);

        parent::load($configs, $container);
    }

    private function initializeKernelParameters(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('kernel.charset')) {
            $container->setParameter('kernel.charset', 'UTF-8');
        }

        if ($container->hasParameter('kernel.enabled_locales')) {
            return;
        }

        $container->setParameter('kernel.enabled_locales', []);
    }
}
