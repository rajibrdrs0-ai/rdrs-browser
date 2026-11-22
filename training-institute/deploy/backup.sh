#!/usr/bin/env bash
# Daily database backup for RTTI. Keeps the last 30 days.
# Install:  sudo cp deploy/backup.sh /usr/local/bin/rtti-backup && sudo chmod 700 /usr/local/bin/rtti-backup
# Cron (02:30 every night):  echo "30 2 * * * root /usr/local/bin/rtti-backup" | sudo tee /etc/cron.d/rtti-backup
# Credentials come from /root/.my.cnf ([mysqldump] user=... password=...), never from this file.
set -euo pipefail
DB="${RTTI_DB:-rtti_management}"
DIR="${RTTI_BACKUP_DIR:-/var/backups/rtti}"
mkdir -p "$DIR" && chmod 700 "$DIR"
FILE="$DIR/${DB}-$(date +%Y%m%d-%H%M).sql.gz"
# --routines/--triggers keep the ledger & audit triggers; --single-transaction = consistent, no locking.
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 "$DB" | gzip -9 > "$FILE"
chmod 600 "$FILE"
find "$DIR" -name "${DB}-*.sql.gz" -mtime +30 -delete
echo "Backup written: $FILE"
