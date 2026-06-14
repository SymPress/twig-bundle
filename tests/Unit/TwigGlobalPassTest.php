<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\TwigBundle\DependencyInjection\Compiler\TwigGlobalPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Environment;

final class TwigGlobalPassTest extends TestCase
{
    public function testAddsTaggedServicesAsTwigGlobalsByPriority(): void
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class);
        $container->register('global.low', \stdClass::class)
            ->addTag('twig.global', ['name' => 'app_config', 'priority' => -10]);
        $container->register('global.high', \stdClass::class)
            ->addTag('twig.global', ['name' => 'app_config', 'priority' => 10]);

        (new TwigGlobalPass())->process($container);

        $calls = $container->getDefinition('twig')->getMethodCalls();

        self::assertSame('addGlobal', $calls[0][0]);
        self::assertSame('app_config', $calls[0][1][0]);
        self::assertInstanceOf(Reference::class, $calls[0][1][1]);
        self::assertSame('global.low', (string) $calls[0][1][1]);
        self::assertSame('global.high', (string) $calls[1][1][1]);
    }
}
