<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\TwigBundle\Attribute\AsTwigGlobal;
use SymPress\TwigBundle\DependencyInjection\TwigExtension;
use SymPress\TwigBundle\Extension\GlobalProviderInterface;
use SymPress\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use SymPress\TwigBundle\Tests\Fixtures\EventPost;
use SymPress\TwigBundle\Tests\Fixtures\PageComposer;
use SymPress\TwigBundle\WordPress\PostFactory;
use SymPress\TwigBundle\WordPress\ThemeRenderer;
use Twig\Environment;
use SymPress\TwigBundle\WordPress\Site;
use SymPress\TwigBundle\Tests\Fixtures\ModelLabel;
use SymPress\TwigBundle\Tests\Fixtures\InjectedPost;
use SymPress\TwigBundle\Tests\Fixtures\InjectedTerm;
use SymPress\TwigBundle\WordPress\TermFactory;

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

    public function testWordPressModelsComposersAndSiteAreWiredInCompiledContainer(): void
    {
        $container = $this->container();
        $container->register(EventPost::class, EventPost::class)->setAutoconfigured(true);
        $container->register(PageComposer::class, PageComposer::class)->setAutoconfigured(true);
        (new TwigBundle())->build($container);
        $container->prependExtensionConfig('sympress_twig', ['wordpress' => ['themes' => ['parent-theme' => []]]]);
        (new TwigExtension())->load([[]], $container);
        $container->getDefinition('twig')->setPublic(true);
        $container->compile();
        $factory = $container->get(PostFactory::class);
        self::assertInstanceOf(PostFactory::class, $factory);
        $post = new \WP_Post();
        $post->post_type = 'event';
        self::assertInstanceOf(EventPost::class, $factory->from($post));
        $entries = $container->getDefinition(ThemeRenderer::class)->getArgument(4);
        self::assertCount(1, $entries);
        self::assertSame(['page-*'], $entries[0]['templates']);
        self::assertSame(42, $entries[0]['priority']);
        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        self::assertInstanceOf(Site::class, $twig->getGlobals()['site']);
        self::assertNull($twig->getFunction('menu'), 'Outside an active WordPress theme standard Twig remains intact.');
    }

    public function testModelConstructorsReceiveServicesAndExplicitScalarBindings(): void
    {
        $container = $this->container();
        $container->register(ModelLabel::class);
        $container->register(InjectedPost::class)
            ->setAutowired(true)->setAutoconfigured(true)->setBindings(['$prefix' => 'bound']);
        $container->register(InjectedTerm::class)
            ->setAutowired(true)->setAutoconfigured(true);
        (new TwigBundle())->build($container);
        (new TwigExtension())->load([[]], $container);
        $container->setAlias('test.terms', TermFactory::class)->setPublic(true);
        $container->compile();
        $native = new \WP_Post();
        $native->post_type = 'injected';
        $factory = $container->get(PostFactory::class);
        self::assertInstanceOf(PostFactory::class, $factory);
        $post = $factory->from($native);
        self::assertInstanceOf(InjectedPost::class, $post);
        self::assertSame('bound injected', $post->label());
        $terms = $container->get('test.terms');
        self::assertInstanceOf(TermFactory::class, $terms);
        $term = $terms->from(new \WP_Term());
        self::assertInstanceOf(InjectedTerm::class, $term);
        self::assertSame('injected', $term->label());
    }

    public function testUnresolvedModelDependencyFailsDuringContainerCompilation(): void
    {
        $container = $this->container();
        $container->register(InjectedPost::class)->setAutoconfigured(true);
        (new TwigBundle())->build($container);
        (new TwigExtension())->load([[]], $container);
        $this->expectException(\InvalidArgumentException::class);
        $container->compile();
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
