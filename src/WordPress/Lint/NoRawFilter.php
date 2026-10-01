<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress\Lint;

use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/** Theme lint rule: trusted HTML belongs at the PHP boundary. */
final class NoRawFilter implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof FilterExpression && $node->getAttribute('name') === 'raw') {
            throw new SyntaxError('The raw filter is forbidden in theme templates. Use WordPress HTML helpers.', $node->getTemplateLine(), $node->getSourceContext());
        }
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
