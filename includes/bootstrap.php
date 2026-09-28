<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/webpush.php';
require_once __DIR__ . '/pay.php';
require_once __DIR__ . '/seed.php';

seed_if_needed();
ensure_extra_data();
