<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;

final class TwigExtensionTest extends TestCase
{
    public function testLoadsSymfonyTwigServicesWithSymPressKernelDefaults(): void
    {
        $container = $this->container();

        (new TwigExtension())->load([[]], $container);

        self::assertSame('UTF-8', $container->getParameter('kernel.charset'));
        self::assertSame([], $container->getParameter('kernel.enabled_locales'));
        self::assertTrue($container->hasDefinition('twig'));
        self::assertTrue($container->hasDefinition('twig.loader.native_filesystem'));
        self::assertTrue($container->hasAlias(Environment::class));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.project_dir', dirname(__DIR__, 3));
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir() . '/sympress-twig-extension-cache');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir() . '/sympress-twig-extension-build');
        $container->setParameter('kernel.container_class', 'KernelContainer');
        $container->setParameter('kernel.bundles_metadata', []);

        return $container;
    }
}
