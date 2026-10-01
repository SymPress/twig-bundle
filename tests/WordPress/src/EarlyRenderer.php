<?php
declare(strict_types=1);
namespace SymPress\TwigFixture;
final readonly class EarlyRenderer
{
    public function __construct(public \Twig\Environment $twig) {}
}
