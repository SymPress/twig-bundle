#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
wp --path=public/wp eval-file integration.php
php admin.php
