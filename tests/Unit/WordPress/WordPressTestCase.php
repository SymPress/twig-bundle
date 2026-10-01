<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Unit\WordPress;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

abstract class WordPressTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        do_action('after_setup_theme');
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
