#!/usr/bin/env bash
# Rilis BRUDEL di VPS, dijalankan dari /var/www/brudel setelah git pull. Berhenti pada kegagalan pertama.
set -euo pipefail

step() { echo; echo "=== $1"; }

step "Kode"
git -C src log --oneline -1

step "Cadangan database"
DB_USER=$(docker compose exec -T db sh -c 'echo ${POSTGRES_USER:-postgres}' | tr -d '\r')
DB_NAME=$(docker compose exec -T db sh -c 'echo ${POSTGRES_DB:-${POSTGRES_USER:-postgres}}' | tr -d '\r')
mkdir -p /root/backup-brudel
BACKUP=/root/backup-brudel/brudel-$(date +%Y%m%d-%H%M%S).dump
docker compose exec -T db pg_dump -U "$DB_USER" -Fc "$DB_NAME" > "$BACKUP"
ENTRIES=$(docker compose exec -T db pg_restore -l < "$BACKUP" | wc -l)
SIZE=$(stat -c %s "$BACKUP")
echo "$BACKUP | $SIZE byte | $ENTRIES entri"
if [ "$SIZE" -lt 100000 ] || [ "$ENTRIES" -lt 50 ]; then
    echo "Cadangan tidak wajar, rilis dihentikan."
    exit 1
fi

step "Migrasi"
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
docker compose restart queue scheduler

step "Pemeriksaan"
for url in / /login /proposal /aset/livewire; do
    printf '%s -> ' "$url"
    curl -s -o /dev/null -w '%{http_code}\n' "http://127.0.0.1:8080$url"
done

echo
echo "DEPLOY SELESAI"
