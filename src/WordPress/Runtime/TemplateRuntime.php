<?php

declare(strict_types=1);

namespace SymPress\TwigBundle\WordPress\Runtime;

use SymPress\TwigBundle\WordPress\Html;
use SymPress\TwigBundle\WordPress\Menu;
use SymPress\TwigBundle\WordPress\Pagination;
use SymPress\TwigBundle\WordPress\Post;
use SymPress\TwigBundle\WordPress\PostScope;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/** @internal */
final class TemplateRuntime
{
    #[AsTwigFunction('wp_head', isSafe: ['html'])]
    public function head(): string
    {
        return Html::capture(wp_head(...));
    }

    #[AsTwigFunction('wp_footer', isSafe: ['html'])]
    public function footer(): string
    {
        return Html::capture(wp_footer(...));
    }

    #[AsTwigFunction('wp_body_open', isSafe: ['html'])]
    public function bodyOpen(): string
    {
        return Html::capture(wp_body_open(...));
    }

    #[AsTwigFunction('language_attributes', isSafe: ['html'])]
    public function languageAttributes(): string
    {
        return get_language_attributes();
    }

    /**
     * @param string|list<string> $extra
     */
    #[AsTwigFunction('body_class', isSafe: ['html'])]
    public function bodyClass(string|array $extra = ''): string
    {
        return Html::capture(static fn () => body_class($extra));
    }

    /**
     * @param string|list<string> $extra
     */
    #[AsTwigFunction('post_class', isSafe: ['html'])]
    public function postClass(string|array $extra = '', ?Post $post = null): string
    {
        return Html::capture(static fn () => post_class($extra, $post?->id()));
    }

    /**
     * @param array{depth?: int} $args
     */
    #[AsTwigFunction('menu')]
    public function menu(string $location, array $args = []): Menu
    {
        return Menu::at($location, $args);
    }

    /**
     * @param array<string, mixed> $args
     */
    #[AsTwigFunction('wp_nav_menu', isSafe: ['html'])]
    public function navMenu(array $args = []): string
    {
        return (string) wp_nav_menu(array_replace($args, ['echo' => false]));
    }

    /**
     * @param array<string, mixed> $args
     */
    #[AsTwigFunction('pagination')]
    public function pagination(array $args = []): Pagination
    {
        return Pagination::forQuery(PostScope::query(), $args);
    }

    #[AsTwigFunction('sidebar', isSafe: ['html'])]
    public function sidebar(string $id): string
    {
        return Html::capture(static fn () => dynamic_sidebar($id));
    }

    #[AsTwigFunction('search_form', isSafe: ['html'])]
    public function searchForm(): string
    {
        return (string) get_search_form(['echo' => false]);
    }

    /**
     * @param array<string, mixed> $args
     */
    #[AsTwigFunction('wp_link_pages', isSafe: ['html'])]
    public function linkPages(array $args = []): string
    {
        return post_password_required() ? '' : wp_link_pages(array_replace($args, ['echo' => false]));
    }

    #[AsTwigFunction('the_password_form', isSafe: ['html'])]
    public function passwordForm(): string
    {
        return get_the_password_form();
    }

    #[AsTwigFunction('comments_template', isSafe: ['html'])]
    public function comments(): string
    {
        if (post_password_required()) {
            return '';
        }
        if (is_singular() && comments_open() && get_option('thread_comments')) {
            wp_enqueue_script('comment-reply');
        }
        return Html::capture(comments_template(...));
    }

    #[AsTwigFunction('action', isSafe: ['html'])]
    public function action(string $name, mixed ...$args): string
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Action name must not be empty.');
        }
        return Html::capture(static fn () => do_action($name, ...$args));
    }

    #[AsTwigFunction('sprintf')]
    public function format(string $format, mixed ...$args): string
    {
        return sprintf($format, ...$args);
    }

    #[AsTwigFilter('wpautop', isSafe: ['html'], preEscape: 'html')]
    public function paragraphs(string $value): string
    {
        return wpautop($value);
    }

    #[AsTwigFilter('shortcodes', isSafe: ['html'])]
    public function shortcodes(string $value): string
    {
        return wp_kses_post(do_shortcode($value));
    }

    #[AsTwigFilter('stripshortcodes')]
    public function stripShortcodes(string $value): string
    {
        return strip_shortcodes($value);
    }

    #[AsTwigFilter('excerpt')]
    public function excerpt(string $value, int $words = 30): string
    {
        return wp_trim_words(Html::text(strip_shortcodes($value)), $words, '…');
    }

    #[AsTwigFilter('time_ago')]
    public function timeAgo(int $timestamp, ?int $now = null): string
    {
        return human_time_diff($timestamp, $now ?? time());
    }

    #[AsTwigFilter('size_format')]
    public function size(int|float|string $bytes, int $decimals = 0): string
    {
        return (string) size_format(is_float($bytes) ? (int) $bytes : $bytes, $decimals);
    }

    #[AsTwigFilter('date')]
    #[AsTwigFilter('wp_date')]
    public function date(\DateTimeInterface|int|string|null $value, ?string $format = null, \DateTimeZone|string|false|null $timezone = null): string
    {
        $zone = $timezone instanceof \DateTimeZone ? $timezone : (is_string($timezone) ? new \DateTimeZone($timezone) : wp_timezone());
        return (string) wp_date(
            $format ?? (string) get_option('date_format'),
            $value instanceof \DateTimeInterface ? $value->getTimestamp() : (is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : (new \DateTimeImmutable($value ?? 'now', $zone))->getTimestamp()),
            $zone,
        );
    }
}
