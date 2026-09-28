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
