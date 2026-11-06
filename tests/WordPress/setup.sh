#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
composer install --no-interaction --prefer-dist
mkdir -p public/wp-content/mu-plugins public/wp-content/themes var
curl --fail --silent --show-error --location \
  https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar \
  --output var/wp-cli.phar
printf '%s  %s\n' ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c var/wp-cli.phar | sha256sum --check
ln -sfn ../wp-config.php public/wp-config.php
ln -sfn ../../../mu-plugin.php public/wp-content/mu-plugins/twig-test.php
ln -sfn ../../../themes/twig-parent public/wp-content/themes/twig-parent
ln -sfn ../../../themes/twig-child public/wp-content/themes/twig-child
php var/wp-cli.phar --path=public/wp core install --url="${TWIG_TEST_URL:-https://sympress-twig.ddev.site}" --title=Twig --admin_user=fixture --admin_password=fixture-only --admin_email=fixture@example.test --skip-email
php var/wp-cli.phar --path=public/wp theme activate twig-child
