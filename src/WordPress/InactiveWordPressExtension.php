<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress;

use Twig\Extension\AbstractExtension;

/** A distinct class keeps active and inactive environments' compiled Twig cache keys separate. */
/** @internal */
final class InactiveWordPressExtension extends AbstractExtension
{
}
