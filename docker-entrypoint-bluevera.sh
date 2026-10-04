#!/bin/sh
set -eu

STORAGE="${BLUEVERA_STORAGE:-/data}"
UPLOADS="$STORAGE/uploads"

mkdir -p "$STORAGE" "$STORAGE/sessions" "$UPLOADS"

# Product images must survive redeploys, so serve uploads from persistent storage.
if [ -e /var/www/html/public/uploads ] && [ ! -L /var/www/html/public/uploads ]; then
    if [ -d /var/www/html/public/uploads ] && [ "$(ls -A /var/www/html/public/uploads 2>/dev/null || true)" ]; then
        cp -an /var/www/html/public/uploads/. "$UPLOADS/" || true
    fi
    rm -rf /var/www/html/public/uploads
fi

ln -sfn "$UPLOADS" /var/www/html/public/uploads

chown -R www-data:www-data "$STORAGE"

exec docker-php-entrypoint "$@"
