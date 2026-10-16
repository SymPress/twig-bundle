<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;

final class HttpFoundationSecurityTest extends TestCase
{
    public function testCacheControlDirectivesSurviveLongWhitespaceBeforeSeparators(): void
    {
        $request = new Request(server: [
            'HTTP_CACHE_CONTROL' => 'private' . str_repeat(' ', 25) . ', max-age=60',
        ]);

        self::assertTrue($request->headers->hasCacheControlDirective('private'));
        self::assertSame('60', $request->headers->getCacheControlDirective('max-age'));
    }

    public function testHeaderWhitespaceSeparatorsRemainSupported(): void
    {
        self::assertSame(['foo', 'bar'], HeaderUtils::split('foo   bar', ' '));
        self::assertSame(['foo bar', 'baz'], HeaderUtils::split("foo bar\tbaz", "\t"));
    }
}
