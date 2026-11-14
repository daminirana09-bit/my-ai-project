#!/usr/bin/env bash
# Builds dist/pharma-sales-crm-<version>.zip with everything needed on the hosting,
# including vendor/, so it can be uploaded through the hosting File Manager without Composer.
# Never includes .env, databases, logs, tests or uploaded files.
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION="${1:-$(date +%Y.%m.%d)}"
OUT="dist/pharma-sales-crm-$VERSION.zip"
BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

git ls-files -co --exclude-standard . | grep -vE '^(tests/|tools/|\.env$|\.env\.(e2e|testing|local|production)$|phpunit\.xml$|database/database\.sqlite|storage/logs/.+\.log)' > "$BUILD/files.txt"
mkdir -p "$BUILD/app"
rsync -a --files-from="$BUILD/files.txt" ./ "$BUILD/app/"
( cd "$BUILD/app" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet )
mkdir -p "$BUILD/app/storage/framework/"{cache/data,sessions,views} "$BUILD/app/storage/logs" "$BUILD/app/storage/app/private" "$BUILD/app/bootstrap/cache"
rm -f "$BUILD/app/bootstrap/cache/"*.php
mkdir -p dist
rm -f "$OUT"
( cd "$BUILD/app" && find . -name .git -type d -prune -exec rm -rf {} + && zip -qr -X "$OLDPWD/$OUT" . -x 'node_modules/*' '*/.git/*' '*/tests/*' '*/docs/*' )
echo "Built $OUT ($(du -h "$OUT" | cut -f1))"
