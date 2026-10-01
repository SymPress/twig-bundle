<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\Tests\Fixtures;

use SymPress\TwigBundle\WordPress\Attribute\AsPostModel;
use SymPress\TwigBundle\WordPress\Post;

#[AsPostModel('event')]
final readonly class EventPost extends Post
{
}
