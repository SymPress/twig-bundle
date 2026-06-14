<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Extension;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class GlobalProviderExtension extends AbstractExtension implements GlobalsInterface
{
    /** @param iterable<GlobalProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /** @return array<string, mixed> */
    public function getGlobals(): array
    {
        $globals = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->getGlobals() as $name => $value) {
                if (!is_string($name) || $name === '') {
                    throw new \LogicException(
                        sprintf('Twig global provider "%s" returned an invalid global name.', $provider::class),
                    );
                }

                $globals[$name] = $value;
            }
        }

        return $globals;
    }
}
