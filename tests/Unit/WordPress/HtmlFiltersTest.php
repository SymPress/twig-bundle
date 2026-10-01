<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit\WordPress;

use Brain\Monkey\Functions;
use SymPress\TwigBundle\WordPress\Runtime\EscapingRuntime;
use SymPress\TwigBundle\WordPress\Runtime\TemplateRuntime;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\Markup;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class HtmlFiltersTest extends WordPressTestCase
{
    public function testParagraphsEscapeUntrustedInputBeforeAddingMarkup(): void
    {
        Functions\when('wpautop')->alias(static fn (string $value): string => '<p>' . $value . '</p>');
        $template = $this->twig()->createTemplate('{{ value|wpautop }}');
        self::assertSame(
            '<p>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; text</p>',
            $template->render(['value' => '<script>alert("x")</script> & text']),
        );
    }

    public function testParagraphsPreserveExplicitlyTrustedMarkup(): void
    {
        Functions\expect('wpautop')->once()->with('<strong>Trusted</strong>')
            ->andReturn('<p><strong>Trusted</strong></p>');
        $template = $this->twig()->createTemplate('{{ value|wpautop }}');
        self::assertSame(
            '<p><strong>Trusted</strong></p>',
            $template->render(['value' => new Markup('<strong>Trusted</strong>', 'UTF-8')]),
        );
    }

    public function testShortcodeOutputCrossesTheWordPressPostHtmlSanitizer(): void
    {
        $input = '<script>input</script>[example]';
        $output = '<script>input</script><strong>Result</strong><img src="x" onerror="alert(1)">';
        Functions\expect('do_shortcode')->once()->with($input)->andReturn($output);
        Functions\expect('wp_kses_post')->once()->with($output)->andReturn('<strong>Result</strong><img src="x">');
        self::assertSame(
            '<strong>Result</strong><img src="x">',
            $this->twig()->createTemplate('{{ value|shortcodes }}')->render(['value' => $input]),
        );
    }

    public function testShortcodeSanitizationDoesNotDependOnTwigAutoescaping(): void
    {
        Functions\expect('do_shortcode')->once()->with('[example]')->andReturn('<script>bad</script><em>Allowed</em>');
        Functions\expect('wp_kses_post')->once()->with('<script>bad</script><em>Allowed</em>')
            ->andReturn('<em>Allowed</em>');
        $template = $this->twig()->createTemplate('{% autoescape false %}{{ value|shortcodes }}{% endautoescape %}');
        self::assertSame('<em>Allowed</em>', $template->render(['value' => '[example]']));
    }

    public function testWordPressDateHasAnExplicitAliasWithTheSameDefaults(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Europe/Berlin'));
        Functions\when('get_option')->justReturn('d.m.Y H:i');
        Functions\when('wp_date')->alias(
            static fn (string $format, int $timestamp, \DateTimeZone $timezone): string => (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format),
        );
        $template = $this->twig()->createTemplate('{{ value|wp_date }}|{{ value|date }}');
        self::assertSame('01.01.1970 01:00|01.01.1970 01:00', $template->render(['value' => 0]));
    }

    public function testWordPressUrlFilterDoesNotEscapeEntitiesTwice(): void
    {
        Functions\when('esc_url')->justReturn('https://x.de/?a=1&#038;b=2');
        $template = $this->twig()->createTemplate('<a href="{{ value|esc_url }}">link</a>');
        $output = $template->render(['value' => 'https://x.de/?a=1&b=2']);
        self::assertSame('<a href="https://x.de/?a=1&#038;b=2">link</a>', $output);
    }

    public function testNullableWordPressFiltersRenderEmptyStrings(): void
    {
        $functions = [
            'esc_html', 'esc_attr', 'esc_url', 'esc_js', 'wp_kses_post', 'wp_kses', 'wpautop',
            'do_shortcode', 'strip_shortcodes', 'wp_strip_all_tags', 'wp_trim_words',
        ];
        foreach ($functions as $function) {
            Functions\when($function)->justReturn('');
        }
        $twig = $this->twig();
        $filters = [
            'esc_html', 'esc_attr', 'esc_url', 'esc_js', 'wp_kses_post', 'wp_kses([])', 'wpautop',
            'shortcodes', 'stripshortcodes', 'excerpt', 'time_ago', 'size_format',
        ];
        foreach ($filters as $filter) {
            $template = $twig->createTemplate('{% autoescape false %}{{ value|' . $filter . ' }}{% endautoescape %}');
            self::assertSame('', $template->render(['value' => null]), $filter);
        }
    }

    private function twig(): Environment
    {
        $twig = new Environment(new ArrayLoader(), ['autoescape' => 'html']);
        $twig->addExtension(new AttributeExtension(TemplateRuntime::class));
        $twig->addExtension(new AttributeExtension(EscapingRuntime::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            TemplateRuntime::class => static fn (): TemplateRuntime => new TemplateRuntime(),
            EscapingRuntime::class => static fn (): EscapingRuntime => new EscapingRuntime(),
        ]));
        return $twig;
    }
}
