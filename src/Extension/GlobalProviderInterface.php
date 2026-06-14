<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Extension;

interface GlobalProviderInterface
{
    /** @return iterable<array-key, mixed> */
    public function getGlobals(): iterable;
}
