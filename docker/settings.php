<?php

/**
 * @file
 * Ustawienia Drupala dla kontenera DEMO. Wszystkie dane dostępowe pochodzą ze zmiennych środowiskowych
 * (w Coolify generowanych automatycznie), w repozytorium nie ma żadnych haseł.
 */

$databases['default']['default'] = [
  'driver' => 'mysql',
  'database' => getenv('DB_NAME') ?: 'drupal',
  'username' => getenv('DB_USER') ?: 'drupal',
  'password' => getenv('DB_PASSWORD') ?: '',
  'host' => getenv('DB_HOST') ?: 'db',
  'port' => getenv('DB_PORT') ?: '3306',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
  'init_commands' => ['isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'],
];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'tylko-lokalnie-zmien-mnie';
$settings['config_sync_directory'] = '../config/sync';
$settings['file_public_path'] = 'sites/default/files';
$settings['update_free_access'] = FALSE;
$settings['allow_authorize_operations'] = FALSE;
$settings['rebuild_access'] = FALSE;
$settings['skip_permissions_hardening'] = TRUE;
$settings['entity_update_batch_size'] = 50;
$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];

// Za odwrotnym proxy Coolify (Traefik): poprawne https i adres klienta.
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'];

$settings['trusted_host_patterns'] = array_filter(explode(',', getenv('DRUPAL_TRUSTED_HOSTS') ?: '^localhost$,^127\.0\.0\.1$,^.+\.sslip\.io$'));

// Demo nie wysyła poczty.
$config['system.mail']['interface']['default'] = 'test_mail_collector';
// Bez komunikatów o błędach na stronie (logi w dblog).
$config['system.logging']['error_level'] = 'hide';
