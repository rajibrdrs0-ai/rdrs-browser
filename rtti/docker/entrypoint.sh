#!/bin/sh
# Writes config/config.php from environment variables, waits for MySQL and
# installs the database on first start. Safe to run on every container start.
set -e
cd /var/www/rtti

: "${DB_HOST:=db}" "${DB_PORT:=3306}" "${DB_NAME:=rtti_training_management}" "${DB_USER:=rtti}" "${DB_PASS:=}"

echo "[rtti] waiting for database ${DB_HOST}:${DB_PORT} ..."
i=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USER"), getenv("DB_PASS")); exit(0);} catch (Throwable $e) { exit(1);}' ; do
  i=$((i+1)); [ "$i" -gt 60 ] && { echo "[rtti] database not reachable"; exit 1; }
  sleep 2
done

INSTALLED=$(php -r 'try { $p = new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_NAME"), getenv("DB_USER"), getenv("DB_PASS")); if (!$p->query("SHOW TABLES LIKE \"users\"")->fetchColumn()) { echo "no"; exit; } echo $p->query("SELECT COUNT(*) FROM system_settings WHERE setting_key = \"installed_at\"")->fetchColumn() ? "yes" : "partial"; } catch (Throwable $e) { echo "no"; }')

if [ "$INSTALLED" = "partial" ]; then
  echo "[rtti] ERROR: the database contains an incomplete installation. Remove the database volume (docker compose down -v) and start again."; exit 1
fi

if [ "$INSTALLED" = "no" ]; then
  if [ -z "$ADMIN_PASSWORD" ]; then
    echo "[rtti] ADMIN_PASSWORD is required for the first start"; exit 1
  fi
  echo "[rtti] first start: installing database ..."
  rm -f config/config.php
  DEMO_FLAG=""; [ "${LOAD_DEMO_DATA:-0}" = "1" ] && DEMO_FLAG="--demo"
  php database/install_cli.php --host="$DB_HOST" --port="$DB_PORT" --db="$DB_NAME" --user="$DB_USER" --pass="$DB_PASS" \
      --admin="${ADMIN_USERNAME:-admin}" --admin-pass="$ADMIN_PASSWORD" --admin-name="${ADMIN_NAME:-System Administrator}" $DEMO_FLAG --force
fi

# (Re)write config from the environment on every start
php -r '
$c = require "config/config.sample.php";
$c["db"] = ["host" => getenv("DB_HOST"), "port" => (int)getenv("DB_PORT"), "name" => getenv("DB_NAME"), "user" => getenv("DB_USER"), "pass" => getenv("DB_PASS"), "charset" => "utf8mb4"];
$c["base_url"] = getenv("BASE_URL") ?: "";
$c["debug"] = getenv("APP_DEBUG") === "1";
file_put_contents("config/config.php", "<?php\nreturn " . var_export($c, true) . ";\n");'
mkdir -p storage/uploads storage/logs storage/backups
touch storage/installed.lock
chown -R www-data:www-data config storage public/assets/img

echo "[rtti] ready"
exec "$@"
