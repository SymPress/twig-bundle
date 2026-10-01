<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit\WordPress;

use Brain\Monkey\Functions;
use SymPress\TwigBundle\WordPress\Excerpt;
use SymPress\TwigBundle\WordPress\Html;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ContentTest extends WordPressTestCase
{
    public function testPlainTextIsDecodedBeforeTwigEscapesItOnce(): void
    {
        Functions\when('wp_strip_all_tags')->alias(strip_tags(...));
        $twig = new Environment(new ArrayLoader(['test' => '{{ text }}']), ['autoescape' => 'html']);
        self::assertSame('A &amp; B', $twig->render('test', ['text' => Html::text('A &amp; B')]));
    }

    public function testExcerptReadsStoredTextWithoutRenderingCallbacks(): void
    {
        Functions\when('wp_strip_all_tags')->alias(strip_tags(...));
        Functions\expect('strip_shortcodes')->once()->with('<p>A &amp; B</p> ')->andReturn('<p>A &amp; B</p> ');
        Functions\expect('wp_trim_words')->once()->with('A & B', 30, '…')->andReturn('A & B');
        Functions\expect('get_the_excerpt')->never();
        Functions\expect('do_shortcode')->never();
        Functions\expect('do_blocks')->never();
        $post = new \WP_Post();
        $post->post_content = '<p>A &amp; B</p>';
        self::assertSame('A & B', Excerpt::plainExcerpt($post, 30));
        $post->post_password = 'secret';
        self::assertSame('', Excerpt::plainExcerpt($post, 30));
    }

    public function testCardsUseWordPressExcerptFiltersAndProtectPrivateText(): void
    {
        Functions\when('wp_trim_words')->returnArg();
        $post = new \WP_Post();
        Functions\expect('get_the_excerpt')->once()->with($post)->andReturn('<b>Filtered &amp; decoded</b>');
        Functions\when('wp_strip_all_tags')->alias(strip_tags(...));
        self::assertSame('Filtered & decoded', Excerpt::fromPost($post));
        $post->post_password = 'secret';
        self::assertSame('', Excerpt::fromPost($post));
    }
}
