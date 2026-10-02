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
    public int $post_author = 1;
    public int $menu_item_parent = 0;
    public string $title = '';
    public string $url = '';
    public bool $current = false;
    /** @var list<string> */
    public array $classes = [];
    public string $target = '';
    public string $xfn = '';
    public string $description = '';
    public string $attr_title = '';
}

class WP_User
{
    public int $ID = 1;
    public string $display_name = 'Public author';
    public string $user_nicename = 'public-author';
    public string $user_email = 'private@example.test';
    public string $user_pass = 'private-password-hash';
}

class WP_Term
{
    public int $term_id = 1;
    public string $name = 'Example';
    public string $slug = 'example';
    public string $taxonomy = 'category';
}

class Walker_Nav_Menu
{
}
