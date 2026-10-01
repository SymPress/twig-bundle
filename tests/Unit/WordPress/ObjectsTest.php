<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit\WordPress;

use Brain\Monkey\Functions;
use SymPress\TwigBundle\WordPress\Image;
use SymPress\TwigBundle\WordPress\Menu;
use SymPress\TwigBundle\WordPress\MetaResolver;
use SymPress\TwigBundle\WordPress\Pagination;
use SymPress\TwigBundle\WordPress\Post;
use SymPress\TwigBundle\WordPress\Runtime\TemplateRuntime;
use SymPress\TwigBundle\WordPress\Runtime\TranslationRuntime;
use SymPress\TwigBundle\WordPress\ThemeConfiguration;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class ObjectsTest extends WordPressTestCase
{
    public function testNativeMetaFallbackAndAcfImageRelationAndDateConversion(): void
    {
        $native = new \WP_Post();
        Functions\when('get_field_object')->justReturn(false);
        Functions\expect('get_post_meta')->once()->with(1, 'plain', true)->andReturn('plain value');
        $meta = new MetaResolver();
        self::assertSame('plain value', $meta->resolve($native, 'plain'));
        Functions\when('get_field_object')->justReturn(['type' => 'image', 'value' => ['ID' => 42]]);
        $image = $meta->resolve($native, 'image');
        self::assertInstanceOf(Image::class, $image);
        self::assertSame(42, $image->id);
        Functions\when('get_field_object')->justReturn(['type' => 'relationship', 'value' => [1]]);
        Functions\when('get_post')->justReturn($native);
        $related = $meta->resolve($native, 'related');
        self::assertInstanceOf(Post::class, $related[0]);
        Functions\when('get_field_object')->justReturn(['type' => 'date_picker', 'return_format' => 'd/m/Y', 'value' => '01/10/2026']);
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Europe/Berlin'));
        $date = $meta->resolve($native, 'date');
        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2026-10-01T00:00:00+02:00', $date->format(DATE_ATOM));
        Functions\when('get_field_object')->justReturn(['type' => 'text', 'value' => '<b>Text</b>']);
        self::assertSame('<b>Text</b>', $meta->resolve($native, 'text'));
    }

    public function testMenusKeepMarkupInTwigAndRespectDepthAndCurrentState(): void
    {
        Functions\when('get_nav_menu_locations')->justReturn(['primary' => 1]);
        $parent = (object) ['ID' => 1, 'menu_item_parent' => 0, 'title' => 'A &amp; B', 'url' => '/?a=1&b=2', 'current' => true, 'classes' => ['current-menu-item'], 'target' => '', 'xfn' => ''];
        $child = clone $parent;
        $child->ID = 2;
        $child->menu_item_parent = 1;
        Functions\when('wp_get_nav_menu_items')->justReturn([$parent, $child]);
        Functions\when('_wp_menu_item_classes_by_context')->justReturn(null);
        Functions\when('wp_strip_all_tags')->alias(strip_tags(...));
        Functions\when('esc_url_raw')->returnArg();
        $menu = Menu::at('primary', ['depth' => 2]);
        self::assertCount(1, $menu->items);
        self::assertSame('A & B', $menu->items[0]->title);
        self::assertTrue($menu->items[0]->current);
        self::assertCount(1, $menu->items[0]->children);
        self::assertSame([], Menu::at('primary', ['depth' => 1])->items[0]->children);
        self::assertSame([], Menu::at('missing')->items);
    }

    public function testPaginationExposesLinksGapsAndCurrentPage(): void
    {
        Functions\when('get_pagenum_link')->alias(static fn (int $page): string => '/page/' . $page . '/');
        Functions\when('add_query_arg')->alias(static fn (array $args, string $url): string => $url);
        Functions\when('esc_url_raw')->returnArg();
        $pagination = Pagination::forQuery(null, ['total' => 9, 'current' => 5, 'prev_text' => 'Back', 'next_text' => 'Next']);
        self::assertSame(5, $pagination->current);
        self::assertSame('/page/4/', $pagination->prev?->url);
        self::assertSame('/page/6/', $pagination->next?->url);
        self::assertSame(['1', '…', '4', '5', '6', '…', '9'], array_column($pagination->pages, 'title'));
        self::assertTrue($pagination->pages[3]->current);
        self::assertNull($pagination->pages[1]->url);
        self::assertSame([], Pagination::forQuery(null)->pages);
    }

    public function testWordPressDateUsesSiteTimezoneAndDefaultFormat(): void
    {
        $zone = new \DateTimeZone('Europe/Berlin');
        Functions\when('wp_timezone')->justReturn($zone);
        Functions\when('get_option')->justReturn('d.m.Y');
        Functions\expect('wp_date')->once()->with('d.m.Y', 1790812800, $zone)->andReturn('01.10.2026');
        self::assertSame('01.10.2026', (new TemplateRuntime())->date(1790812800));
    }

    public function testTranslationsAreEscapedAndArbitraryFunctionsAreUnavailable(): void
    {
        Functions\when('__')->justReturn('<b>A & B</b>');
        $twig = new Environment(new ArrayLoader(['test' => '{{ __("Text", "custom") }}']), ['autoescape' => 'html']);
        $twig->addExtension(new AttributeExtension(TranslationRuntime::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([TranslationRuntime::class => static fn (): TranslationRuntime => new TranslationRuntime(new ThemeConfiguration())]));
        self::assertSame('&lt;b&gt;A &amp; B&lt;/b&gt;', $twig->render('test'));
        self::assertNull($twig->getFunction('fn'));
        self::assertNull($twig->getFunction('function'));
    }
}
