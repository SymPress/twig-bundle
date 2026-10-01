#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
composer install --no-interaction --prefer-dist
mkdir -p public/wp-content/mu-plugins public/wp-content/themes var
ln -sfn ../wp-config.php public/wp-config.php
ln -sfn ../../../mu-plugin.php public/wp-content/mu-plugins/twig-test.php
ln -sfn ../../../themes/twig-parent public/wp-content/themes/twig-parent
ln -sfn ../../../themes/twig-child public/wp-content/themes/twig-child
wp --path=public/wp core install --url="${TWIG_TEST_URL:-https://sympress-twig.ddev.site}" --title=Twig --admin_user=fixture --admin_password=fixture-only --admin_email=fixture@example.test --skip-email
wp --path=public/wp theme activate twig-child
