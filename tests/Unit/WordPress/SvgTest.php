<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit\WordPress;

use Brain\Monkey\Functions;
use SymPress\TwigBundle\WordPress\Runtime\TemplateRuntime;
use SymPress\TwigBundle\WordPress\Svg;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class SvgTest extends WordPressTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/sympress-svg-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/child/icons', 0777, true);
        mkdir($this->root . '/parent/icons', 0777, true);
        $child = $this->root . '/child';
        $parent = $this->root . '/parent';
        Functions\when('get_stylesheet_directory')->justReturn($child);
        Functions\when('get_template_directory')->justReturn($parent);
        // Mirrors WordPress: the child theme file wins when it exists.
        Functions\when('get_theme_file_path')->alias(
            static fn (string $file): string => (file_exists($child . '/' . $file) ? $child : $parent) . '/' . $file,
        );
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
        parent::tearDown();
    }

    public function testChildThemeFileReplacesParentAndParentIsTheFallback(): void
    {
        $this->file('parent/icons/menu.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1"/></svg>');
        $this->file('parent/icons/close.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M2 2h1"/></svg>');
        $this->file('child/icons/menu.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M9 9h1"/></svg>');

        self::assertStringContainsString('M9 9h1', (string) Svg::inline('icons/menu'));
        self::assertStringContainsString('M2 2h1', (string) Svg::inline('icons/close.svg'));
    }

    public function testExportedMarkupLosesScriptsHandlersExternalReferencesAndEditorData(): void
    {
        $this->file('parent/icons/unsafe.svg', <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                 xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" viewBox="0 0 24 24" onload="alert(1)">
              <!-- exported by an editor -->
              <style>.st0{fill:red}</style>
              <script>alert(2)</script>
              <inkscape:grid/>
              <defs><linearGradient id="g"><stop offset="0" stop-color="#000"/></linearGradient></defs>
              <path class="st0" d="M0 0h24" fill="url(#g)" inkscape:label="Layer" onclick="alert(3)"/>
              <rect width="1" height="1" fill="url(https://tracker.example/x.svg#a)" style="fill:\75rl(x)"/>
              <use href="#g" xlink:href="javascript:alert(4)"/>
              <use href="https://evil.example/sprite.svg#icon"/>
              <a href="javascript:alert(5)"><circle r="1"/></a>
              <foreignObject><div>html</div></foreignObject>
              <image href="https://tracker.example/pixel.png"/>
              <animate attributeName="href" to="javascript:alert(6)"/>
            </svg>
            SVG);

        $html = (string) Svg::inline('icons/unsafe');

        $removed = [
            'alert', 'script', '<style', 'inkscape:grid', 'inkscape:label', 'tracker.example', 'evil.example',
            'javascript', '<a', 'foreignObject', '<image', '<animate', '<!--', 'DOCTYPE', '<?xml', '\\',
        ];
        foreach ($removed as $needle) {
            self::assertStringNotContainsString($needle, $html, $needle);
        }
        self::assertStringContainsString('fill="url(#g)"', $html);
        self::assertStringContainsString('<use href="#g"/>', $html);
        self::assertStringContainsString('viewBox="0 0 24 24"', $html);
        self::assertStringContainsString('<stop offset="0" stop-color="#000"/>', $html);
    }

    public function testEntityDeclarationsAreRefused(): void
    {
        $doctype = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]>';
        $this->file('parent/icons/xxe.svg', $doctype . self::svg('<text>&x;</text>'));
        Functions\expect('wp_trigger_error')->once();

        self::assertSame('', (string) Svg::inline('icons/xxe'));
    }

    public function testAttributesSharingALocalNameAreAllChecked(): void
    {
        $this->file('parent/icons/collide.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:x="urn:x" xmlns:xlink="http://www.w3.org/1999/xlink"
                 onload="alert(1)" x:onload="1">
              <use xlink:href="#ok" href="https://evil.example/sprite.svg#icon"/>
            </svg>
            SVG);

        $html = (string) Svg::inline('icons/collide');

        self::assertStringNotContainsString('onload', $html);
        self::assertStringNotContainsString('evil.example', $html);
        self::assertStringContainsString('xlink:href="#ok"', $html);
    }

    public function testEntityDeclarationsAreRefusedInAnyEncoding(): void
    {
        $doctype = '<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE svg [<!ENTITY x "boom">]>';
        $xml = $doctype . self::svg('<text>&x;</text>');
        $this->file('parent/icons/utf16.svg', "\xFF\xFE" . mb_convert_encoding($xml, 'UTF-16LE', 'UTF-8'));
        Functions\expect('wp_trigger_error')->once();

        self::assertSame('', (string) Svg::inline('icons/utf16'));
    }

    public function testCssFunctionsLoadingExternalImagesAreRemoved(): void
    {
        $this->file('parent/icons/css.svg', self::svg(
            '<rect style="fill:red;background:image-set(\'https://tracker.example/a.png\' 1x)"/>'
            . '<rect style="background:-webkit-image-set(&quot;https://tracker.example/b.png&quot; 1x)"/>'
            . '<rect style="fill:#000;stroke:url(#g)"/>',
        ));

        $html = (string) Svg::inline('icons/css');

        self::assertStringNotContainsString('tracker.example', $html);
        self::assertStringContainsString('style="fill:#000;stroke:url(#g)"', $html);
    }

    public function testBooleanAriaValuesUseAriaTokens(): void
    {
        $this->file('parent/icons/menu.svg', self::svg('<path/>'));

        $hidden = (string) Svg::inline('icons/menu', ['aria-hidden' => true]);
        $exposed = (string) Svg::inline('icons/menu', ['aria-hidden' => false]);

        self::assertStringContainsString('aria-hidden="true"', $hidden);
        self::assertStringContainsString('aria-hidden="false"', $exposed);
    }

    public function testOnlyARootTitleNamesTheIcon(): void
    {
        $this->file('parent/icons/layer.svg', self::svg('<g><title>Layer 1</title><path/></g>'));

        self::assertStringContainsString('aria-hidden="true"', (string) Svg::inline('icons/layer'));
    }

    public function testTemplateAttributeValuesMustBeTextual(): void
    {
        $this->file('parent/icons/menu.svg', self::svg('<path/>'));
        $label = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'Menü';
            }
        };

        $html = (string) Svg::inline('icons/menu', ['aria-label' => $label]);

        self::assertStringContainsString('aria-label="Menü"', $html);
        foreach ([['class' => ['icon']], [0 => 'icon']] as $attributes) {
            try {
                Svg::inline('icons/menu', $attributes);
                self::fail('Accepted ' . json_encode($attributes));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTemplateAttributesAreValidatedAndEscaped(): void
    {
        $this->file('parent/icons/menu.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10"><path/></svg>');

        $html = (string) Svg::inline(
            'icons/menu',
            ['class' => 'icon "big"', 'aria-label' => 'Menü', 'width' => null, 'height' => 24],
        );

        self::assertStringContainsString('class="icon &quot;big&quot;"', $html);
        self::assertStringContainsString('aria-label="Menü"', $html);
        self::assertStringContainsString('height="24"', $html);
        self::assertStringNotContainsString('width=', $html);
        self::assertStringNotContainsString('aria-hidden', $html);

        $this->expectException(\InvalidArgumentException::class);
        Svg::inline('icons/menu', ['onclick' => 'alert(1)']);
    }

    public function testUnnamedIconsAreHiddenFromAssistiveTechnology(): void
    {
        $this->file('parent/icons/plain.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>');
        $this->file('parent/icons/titled.svg', self::svg('<title>Suche</title><path d="M0 0"/>'));

        self::assertStringContainsString('aria-hidden="true"', (string) Svg::inline('icons/plain'));
        self::assertStringNotContainsString('aria-hidden', (string) Svg::inline('icons/titled'));
    }

    public function testTraversalAndAbsoluteNamesAreRejected(): void
    {
        foreach (['../wp-config', '/etc/passwd', 'icons/../../secret', 'icons\\menu', ''] as $name) {
            try {
                Svg::inline($name);
                self::fail('Accepted ' . $name);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testMissingFilesAndSymlinksOutOfTheThemeRenderNothing(): void
    {
        $this->file('outside.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>');
        symlink($this->root . '/outside.svg', $this->root . '/parent/icons/link.svg');
        Functions\expect('wp_trigger_error')->twice();

        self::assertSame('', (string) Svg::inline('icons/missing'));
        self::assertSame('', (string) Svg::inline('icons/link'));
    }

    public function testTwigOutputsTheSanitizedMarkupWithoutEscapingIt(): void
    {
        $this->file('parent/icons/menu.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>');
        $twig = new Environment(new ArrayLoader(), ['autoescape' => 'html']);
        $twig->addExtension(new AttributeExtension(TemplateRuntime::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            TemplateRuntime::class => static fn (): TemplateRuntime => new TemplateRuntime(),
        ]));

        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" class="icon" aria-hidden="true"><path d="M0 0"/></svg>',
            $twig->createTemplate("{{ svg('icons/menu', {class: 'icon'}) }}")->render(),
        );
    }

    private static function svg(string $body): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg">' . $body . '</svg>';
    }

    private function file(string $relative, string $content): void
    {
        file_put_contents($this->root . '/' . $relative, $content);
    }
}
