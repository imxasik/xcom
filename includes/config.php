<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Dhaka');

define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('APP_KEY', 'orbit-offerhub-bd-2026-k9x2m');
define('SESSION_DAYS', 30);
define('LOGIN_MAX_TRIES', 5);
define('LOGIN_LOCK_MINUTES', 15);
define('POLL_SECONDS', 5);
define('JSON_FLAGS', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
define('JSON_FLAGS_PRETTY', JSON_FLAGS);

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0775, true);
}

/*
 * Never let PHP warnings/notices leak into responses. Previously a stray notice
 * would print raw text on top of the app (and corrupt JSON API replies so every
 * action showed "সার্ভার উত্তর পাওয়া যায়নি"). Log them instead of displaying.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
if (is_dir(DATA_DIR) && is_writable(DATA_DIR)) {
    ini_set('error_log', DATA_DIR . '/php-error.log');
}
