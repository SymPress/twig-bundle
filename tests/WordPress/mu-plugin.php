<?php
declare(strict_types=1);
if (!wp_installing()) {
    \SymPress\Kernel\App::bootKernel(new \SymPress\Kernel\Kernel\SiteKernel(__DIR__, 'test', true));
}
