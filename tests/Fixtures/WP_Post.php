<?php

declare(strict_types=1);

// Data only; real loop semantics are also tested against the WordPress fixture.
class WP_Post
{
    public int $ID = 1;
    public string $post_password = '';
    public string $post_excerpt = '';
    public string $post_content = '';
    public string $post_type = 'post';
    public string $post_status = 'publish';
    public string $post_name = 'example';
    public string $post_mime_type = '';
}
