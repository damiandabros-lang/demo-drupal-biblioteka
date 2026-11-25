#!/bin/sh
# Pierwsze uruchomienie: instalacja Drupala i dane demonstracyjne. Kolejne: tylko czyszczenie pamięci podręcznej.
set -e
cd /opt/drupal
DRUSH=vendor/bin/drush

echo "[demo] czekam na bazę danych ${DB_HOST:-db}..."
i=0
until php -r 'try { new PDO("mysql:host=".(getenv("DB_HOST")?:"db").";port=".(getenv("DB_PORT")?:"3306").";dbname=".(getenv("DB_NAME")?:"drupal"), getenv("DB_USER")?:"drupal", getenv("DB_PASSWORD")?:""); } catch (Throwable $e) { exit(1); }'; do
  i=$((i+1)); [ "$i" -gt 90 ] && { echo "[demo] baza niedostępna"; exit 1; }
  sleep 2
done

mkdir -p web/sites/default/files
chown -R www-data:www-data web/sites/default/files

if [ "$($DRUSH status --field=bootstrap 2>/dev/null)" != "Successful" ]; then
  echo "[demo] instalacja Drupala..."
  if [ -z "$DRUPAL_ADMIN_PASSWORD" ]; then echo "[demo] brak DRUPAL_ADMIN_PASSWORD"; exit 1; fi
  $DRUSH site:install standard -y --account-name=admin --account-pass="$DRUPAL_ADMIN_PASSWORD" \
    --account-mail=demo@example.invalid --site-mail=demo@example.invalid --site-name="Biblioteka DEMO" >/dev/null
  $DRUSH pm:install -y isvero_demo
  $DRUSH php:script scripts/seed.php
  echo "[demo] instalacja zakończona (login: admin, hasło w zmiennej Coolify SERVICE_PASSWORD_ADMIN)"
else
  $DRUSH updatedb -y || true
fi
$DRUSH cache:rebuild
chown -R www-data:www-data web/sites/default/files
exec apache2-foreground
