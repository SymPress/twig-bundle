<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit\WordPress;

use Brain\Monkey\Functions;
use SymPress\TwigBundle\DependencyInjection\SymPressTwigExtension;
use SymPress\TwigBundle\WordPress\Html;
use SymPress\TwigBundle\WordPress\MetaResolver;
use SymPress\TwigBundle\WordPress\Post;
use SymPress\TwigBundle\WordPress\PostFactory;
use SymPress\TwigBundle\WordPress\QueryContextProvider;
use SymPress\TwigBundle\WordPress\TemplateComposerInterface;
use SymPress\TwigBundle\WordPress\TemplateContext;
use SymPress\TwigBundle\WordPress\TemplateHierarchy;
use SymPress\TwigBundle\WordPress\TemplateInclude;
use SymPress\TwigBundle\WordPress\ThemeConfiguration;
use SymPress\TwigBundle\WordPress\ThemeRenderer;
use SymPress\TwigBundle\WordPress\Lint\NoRawFilter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;
use SymPress\TwigBundle\WordPress\CustomTemplates;
use SymPress\TwigBundle\WordPress\Integration;
use SymPress\TwigBundle\WordPress\WordPressExtension;

final class IntegrationTest extends WordPressTestCase
{
    public function testConfigurationPreservesHyphenatedSlugs(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        (new SymPressTwigExtension())->load([['wordpress' => ['themes' => ['parent-theme' => [], 'other-theme' => []]]]], $container);
        $config = $container->get(ThemeConfiguration::class);
        self::assertInstanceOf(ThemeConfiguration::class, $config);
        self::assertArrayHasKey('parent-theme', $config->themes);
    }

    public function testOnlyActiveThemeConfigurationIsUsedAndChildInheritsParent(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_is_block_theme')->justReturn(false);
        Functions\when('get_template')->justReturn('parent-theme');
        Functions\when('get_stylesheet')->justReturn('child-theme');
        Functions\when('get_stylesheet_directory')->justReturn('/child');
        Functions\when('get_template_directory')->justReturn('/parent');
        $config = new ThemeConfiguration(['other-theme' => ['views_dir' => 'wrong'], 'parent-theme' => []]);
        self::assertSame(['/child/resources/views', '/parent/resources/views'], $config->paths());
        $child = new ThemeConfiguration(['parent-theme' => [], 'child-theme' => ['views_dir' => 'views']]);
        self::assertSame(['/child/views', '/parent/views'], $child->paths());
        self::assertNull((new ThemeConfiguration(['other-theme' => []]))->active());
        Functions\when('is_admin')->justReturn(true);
        self::assertNull($config->active());
        self::assertNotNull($config->selected(), 'Editor template discovery retains the active theme registration.');
        self::assertSame(['/child/resources/views', '/parent/resources/views'], $config->paths());
        Functions\when('wp_doing_ajax')->justReturn(true);
        self::assertNotNull($config->active(), 'Explicit AJAX rendering remains available.');
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_is_block_theme')->justReturn(true);
        self::assertNull($config->active());
    }

    public function testHierarchyObservesNativeFiltersWithoutReplayingGetters(): void
    {
        Functions\expect('get_single_template')->never();
        $hierarchy = new TemplateHierarchy();
        $input = ['single-book-example.php', 'single-book.php', 'single.php'];
        self::assertSame($input, $hierarchy->capture($input));
        $hierarchy->capture(['singular.php']);
        self::assertSame(['single-book-example', 'single-book', 'single', 'singular', 'index'], $hierarchy->current());
        self::assertSame(['custom/landing', 'index'], TemplateHierarchy::normalize(['../secret', '/etc/passwd', 'a/../b', 'a..b', 'x\\y', '@other/file', 'php:filter', "x\0y", 'custom/landing.html.twig', 'index.php']));
    }

    public function testChildThemeOverridesParentViewsAndCustomTemplates(): void
    {
        $root = sys_get_temp_dir() . '/sympress-child-' . bin2hex(random_bytes(6));
        foreach (['parent', 'child'] as $theme) {
            mkdir($root . '/' . $theme . '/resources/views/custom', 0777, true);
            file_put_contents($root . '/' . $theme . '/resources/views/index.html.twig', $theme);
            file_put_contents($root . '/' . $theme . '/resources/views/custom/landing.html.twig', $theme);
        }
        file_put_contents($root . '/parent/resources/views/parent-only.html.twig', 'inherited');
        try {
            Functions\when('is_admin')->justReturn(false);
            Functions\when('wp_is_block_theme')->justReturn(false);
            Functions\when('get_template')->justReturn('parent');
            Functions\when('get_stylesheet')->justReturn('child');
            Functions\when('get_template_directory')->justReturn($root . '/parent');
            Functions\when('get_stylesheet_directory')->justReturn($root . '/child');
            Functions\when('get_file_data')->alias(static fn (string $file): array => ['name' => str_contains($file, '/child/') ? 'Child template' : 'Parent template', 'types' => 'page, event']);
            $config = new ThemeConfiguration(['parent' => [], 'unrelated' => []]);
            $loader = new FilesystemLoader();
            $twig = new Environment($loader);
            $twig->addExtension(new WordPressExtension($config));
            self::assertSame('early controller', $twig->createTemplate('early controller')->render());
            $hierarchy = new TemplateHierarchy();
            $renderer = new ThemeRenderer($twig, $hierarchy, new QueryContextProvider(new PostFactory(new MetaResolver())), $config);
            $custom = new CustomTemplates($config);
            $integration = new Integration($config, $twig, $loader, $hierarchy, new TemplateInclude($hierarchy, $renderer));
            $integration->activate();
            $integration->activate();
            self::assertSame('child', $renderer->render('index'));
            self::assertSame('inherited', $renderer->render('parent-only'));
            self::assertSame(['custom/landing.html.twig' => 'Child template'], $custom->templates([], 'event'));
            self::assertSame([], $custom->templates([], 'post'));
            self::assertTrue($loader->exists('@wordpress/document.html.twig'));
            Functions\when('is_admin')->justReturn(true);
            self::assertSame(['custom/landing.html.twig' => 'Child template'], $custom->templates([], 'page'));
        } finally {
            unlink($root . '/parent/resources/views/parent-only.html.twig');
            foreach (['parent', 'child'] as $theme) {
                unlink($root . '/' . $theme . '/resources/views/index.html.twig');
                unlink($root . '/' . $theme . '/resources/views/custom/landing.html.twig');
                foreach (['/resources/views/custom', '/resources/views', '/resources', ''] as $directory) {
                    rmdir($root . '/' . $theme . $directory);
                }
            }
            rmdir($root);
        }
    }

    public function testPluginTemplatesAndMoreSpecificPhpArePreserved(): void
    {
        $root = sys_get_temp_dir() . '/sympress-twig-' . bin2hex(random_bytes(6));
        mkdir($root);
        mkdir($root . '/parent');
        foreach (['plugin.php', 'parent/single.php', 'parent/index.php'] as $file) {
            file_put_contents($root . '/' . $file, '<?php');
        }
        try {
            Functions\when('get_template_directory')->justReturn($root . '/parent');
            Functions\when('get_stylesheet_directory')->justReturn($root . '/parent');
            $hierarchy = new TemplateHierarchy();
            $hierarchy->capture(['single.php', 'singular.php', 'index.php']);
            $renderer = $this->renderer(['@theme/singular.html.twig' => 'single'], $hierarchy);
            $include = new TemplateInclude($hierarchy, $renderer);
            self::assertSame($root . '/plugin.php', $include->filter($root . '/plugin.php'));
            self::assertSame($root . '/parent/single.php', $include->filter($root . '/parent/single.php'));
            self::assertStringEndsWith('/Resources/wordpress/template.php', $include->filter($root . '/parent/index.php'));
            $equal = new TemplateInclude($hierarchy, $this->renderer(['@theme/single.html.twig' => 'single'], $hierarchy));
            self::assertStringEndsWith('/Resources/wordpress/template.php', $equal->filter($root . '/parent/single.php'));
        } finally {
            foreach (['plugin.php', 'parent/single.php', 'parent/index.php'] as $file) {
                unlink($root . '/' . $file);
            }
            rmdir($root . '/parent');
            rmdir($root);
        }
    }

    public function testComposersMatchHierarchyNamesAndMergeInPriorityOrder(): void
    {
        $composer = new class implements TemplateComposerInterface {
            public function compose(TemplateContext $context): array
            {
                return ['message' => 'composer', 'post_present' => $context->post() !== null];
            }
        };
        $twig = new Environment(new ArrayLoader(['@theme/index.html.twig' => '{% block content %}{{ message }}{% endblock %}']));
        $renderer = new ThemeRenderer($twig, new TemplateHierarchy(), new QueryContextProvider(new PostFactory(new MetaResolver())), new ThemeConfiguration(), [
            ['composer' => $composer, 'templates' => ['page-*'], 'priority' => 10],
        ]);
        self::assertSame('composer', $renderer->render(['page-contact'], ['message' => 'default']));
        self::assertSame('default', $renderer->renderBlock('single', 'content', ['message' => 'default']));
        self::assertSame('@theme/index.html.twig', $renderer->resolved());
    }

    public function testPostContentIsLazyMemoizedAndPasswordAware(): void
    {
        $native = new \WP_Post();
        Functions\when('is_admin')->justReturn(false);
        Functions\when('post_password_required')->justReturn(false);
        Functions\expect('get_the_content')->once()->andReturn('<p>Content</p>');
        $post = new Post($native, new MetaResolver());
        self::assertSame(1, $post->id());
        self::assertSame('<p>Content</p>', (string) $post->content());
        self::assertSame($post->content(), $post->content());
        Functions\when('post_password_required')->justReturn(true);
        Functions\expect('get_the_password_form')->once()->andReturn('<form>Password</form>');
        $protected = new Post($native, new MetaResolver());
        self::assertSame('<form>Password</form>', (string) $protected->content());
        self::assertSame('', (string) $protected->thumbnail());
        self::assertNull($protected->meta('secret'));
    }

    public function testOutputBufferIsRestoredAfterException(): void
    {
        $level = ob_get_level();
        try {
            Html::capture(static function (): void {
                echo 'partial';
                throw new \RuntimeException('stop');
            });
            self::fail('Expected exception');
        } catch (\RuntimeException $error) {
            self::assertSame('stop', $error->getMessage());
            self::assertSame($level, ob_get_level());
        }
    }

    public function testRawFilterCannotBypassThemeEscaping(): void
    {
        $twig = new Environment(new ArrayLoader(['test' => '{{ value|raw }}']));
        $twig->addNodeVisitor(new NoRawFilter());
        $this->expectException(SyntaxError::class);
        $twig->render('test', ['value' => '<script>bad</script>']);
    }

    public function testInactiveAndActiveExtensionsHaveSeparateCompiledCacheKeys(): void
    {
        Functions\when('get_stylesheet')->justReturn('parent');
        Functions\when('get_template')->justReturn('parent');
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_is_block_theme')->justReturn(false);
        $config = new ThemeConfiguration(['parent' => []]);
        $loader = new ArrayLoader(['date' => '{{ "now"|date }}']);
        $active = new Environment($loader);
        $active->addExtension(WordPressExtension::create($config));
        Functions\when('is_admin')->justReturn(true);
        $inactive = new Environment($loader);
        $inactive->addExtension(WordPressExtension::create($config));
        self::assertNotSame($active->getTemplateClass('date'), $inactive->getTemplateClass('date'));
        self::assertNull($inactive->getFunction('menu'));
    }

    /** @param array<string, string> $templates */
    private function renderer(array $templates, TemplateHierarchy $hierarchy): ThemeRenderer
    {
        return new ThemeRenderer(new Environment(new ArrayLoader($templates)), $hierarchy, new QueryContextProvider(new PostFactory(new MetaResolver())), new ThemeConfiguration());
    }
}
