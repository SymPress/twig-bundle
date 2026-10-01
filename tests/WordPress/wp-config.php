<?php
declare(strict_types=1);
// Public disposable fixture credentials only.
define('DB_NAME', getenv('TWIG_TEST_DB_NAME') ?: 'db');
define('DB_USER', getenv('TWIG_TEST_DB_USER') ?: 'db');
define('DB_PASSWORD', getenv('TWIG_TEST_DB_PASSWORD') ?: 'db');
define('DB_HOST', getenv('TWIG_TEST_DB_HOST') ?: 'db');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$table_prefix = 'twig_';
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_HOME', getenv('TWIG_TEST_URL') ?: 'https://sympress-twig.ddev.site');
define('WP_SITEURL', WP_HOME . '/wp');
define('WP_CONTENT_DIR', __DIR__ . '/public/wp-content');
define('WP_CONTENT_URL', WP_HOME . '/wp-content');
define('DISABLE_WP_CRON', true);
require __DIR__ . '/vendor/autoload.php';
defined('ABSPATH') || define('ABSPATH', __DIR__ . '/public/wp/');
require ABSPATH . 'wp-settings.php';
