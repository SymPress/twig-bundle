<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\TwigBundle\Attribute\AsTwigGlobal;
use SymPress\TwigBundle\DependencyInjection\TwigExtension;
use SymPress\TwigBundle\Extension\GlobalProviderInterface;
use SymPress\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TwigBundleTest extends TestCase
{
    public function testRegistersSymPressTwigAutoconfiguration(): void
    {
        $container = $this->container();
        $container->register(GlobalProviderFixture::class, GlobalProviderFixture::class)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->register(GlobalFixture::class, GlobalFixture::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        (new TwigBundle())->build($container);
        (new TwigExtension())->load([[]], $container);
        $container->compile();

        self::assertTrue($container->getDefinition(GlobalProviderFixture::class)->hasTag('twig.global_provider'));
        self::assertSame(
            [['name' => 'example', 'priority' => 5]],
            $container->getDefinition(GlobalFixture::class)->getTag('twig.global'),
        );
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.project_dir', dirname(__DIR__, 3));
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir() . '/sympress-twig-bundle-cache');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir() . '/sympress-twig-bundle-build');
        $container->setParameter('kernel.container_class', 'KernelContainer');
        $container->setParameter('kernel.bundles_metadata', []);
        $container->register('error_renderer.html', \stdClass::class);

        return $container;
    }
}

final class GlobalProviderFixture implements GlobalProviderInterface
{
    public function getGlobals(): iterable
    {
        return ['provider_global' => 'value'];
    }
}

#[AsTwigGlobal('example', priority: 5)]
final class GlobalFixture
{
}
