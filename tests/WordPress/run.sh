#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
php var/wp-cli.phar --path=public/wp eval-file integration.php
php admin.php
