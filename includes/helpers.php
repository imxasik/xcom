<?php
declare(strict_types=1);

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function uid(string $prefix = ''): string
{
    $id = strtoupper(bin2hex(random_bytes(6)));
    return $prefix !== '' ? $prefix . $id : $id;
}

function order_code(): string
{
    return 'TH-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

function oh_resolve_action(array $input = []): string
{
    if (defined('OH_FORCE_ACTION') && is_string(OH_FORCE_ACTION) && OH_FORCE_ACTION !== '') {
        return strtolower(OH_FORCE_ACTION);
    }
    $aliases = [
        'pay_bill' => 'bill', 'paybill' => 'bill', 'bill_pay' => 'bill', 'billpay' => 'bill',
        'user_bill' => 'bill', 'userbill' => 'bill', 'dobill' => 'bill',
        'wd' => 'withdraw', 'cashout' => 'withdraw', 'user_withdraw' => 'withdraw',
        'userwithdraw' => 'withdraw', 'dowithdraw' => 'withdraw',
        'xfer' => 'transfer', 'p2p' => 'transfer', 'send' => 'transfer',
        'user_transfer' => 'transfer', 'usertransfer' => 'transfer', 'dotransfer' => 'transfer',
        'notificationsread' => 'notifications_read',
        'adminlogin' => 'admin_login', 'adminlogout' => 'admin_logout', 'adminboot' => 'admin_boot',
        'adminstats' => 'admin_stats', 'adminorders' => 'admin_orders', 'adminorder' => 'admin_order',
        'adminoffers' => 'admin_offers', 'adminoffer' => 'admin_offer', 'adminofferdel' => 'admin_offer_del',
        'adminusers' => 'admin_users', 'adminuser' => 'admin_user', 'adminpayments' => 'admin_payments',
        'adminpayment' => 'admin_payment', 'adminmethods' => 'admin_methods',
        'adminoperators' => 'admin_operators', 'adminnotify' => 'admin_notify',
        'adminlogs' => 'admin_logs', 'adminsettings' => 'admin_settings',
        'admincopy' => 'admin_copy', 'adminbalance' => 'admin_balance',
        'adminbills' => 'admin_bills', 'gatewayhook' => 'gateway_hook',
    ];
    $norm = static function ($v) use ($aliases): string {
        if (is_array($v)) {
            $v = reset($v);
        }
        $v = strtolower(trim((string)$v));
        $v = preg_replace('/\.php$/i', '', $v) ?? $v;
        $v = preg_replace('/[^a-z0-9_]+/', '', $v) ?? '';
        if ($v === '' || in_array($v, ['api', 'index', 'nx', 'oh'], true)) {
            return '';
        }
        $compact = str_replace('_', '', $v);
        return $aliases[$v] ?? $aliases[$compact] ?? $v;
    };

    foreach ([
        basename((string)($_SERVER['SCRIPT_NAME'] ?? '')),
        basename((string)($_SERVER['PHP_SELF'] ?? '')),
        basename((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '')),
        ltrim((string)($_SERVER['PATH_INFO'] ?? ''), '/'),
    ] as $src) {
        $a = $norm($src);
        if (in_array($a, ['bill', 'withdraw', 'transfer'], true)) {
            return $a;
        }
    }

    foreach ([
        $_GET['action'] ?? '',
        $_POST['action'] ?? '',
        $input['action'] ?? '',
        $_GET['do'] ?? '',
        $_POST['do'] ?? '',
        $input['do'] ?? '',
        $_SERVER['HTTP_X_OH_ACTION'] ?? '',
        $_SERVER['HTTP_X_ACTION'] ?? '',
    ] as $c) {
        $a = $norm($c);
        if ($a !== '') {
            return $a;
        }
    }
    if (!empty($_SERVER['QUERY_STRING'])) {
        parse_str((string)$_SERVER['QUERY_STRING'], $qs);
        $a = $norm($qs['action'] ?? $qs['do'] ?? '');
        if ($a !== '') {
            return $a;
        }
    }
    if (!empty($input['provider']) && (isset($input['account']) || isset($input['amount']))) {
        return 'bill';
    }
    if (!empty($input['phone']) && isset($input['amount']) && empty($input['trx']) && empty($input['number'])) {
        return 'transfer';
    }
    if (!empty($input['method']) && isset($input['amount']) && empty($input['trx'])) {
        return 'withdraw';
    }
    return '';
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body_json(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
        if ($raw !== '' && strpos($raw, '=') !== false) {
            parse_str($raw, $parsed);
            if (is_array($parsed)) {
                $data = $parsed;
            }
        }
    }
    if (!empty($_POST) && is_array($_POST)) {
        $data = array_merge($_POST, $data);
    }
    $cached = $data;
    return $cached;
}

function mb_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

function mb_cut(string $s, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max);
    }
    return substr($s, 0, $max);
}

function str_clean($v, int $max = 255): string
{
    $s = trim((string)$v);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
    if (mb_len($s) > $max) {
        $s = mb_cut($s, $max);
    }
    return $s;
}

function money($n): float
{
    if (is_string($n)) {
        $n = str_replace([',', '৳', 'tk', 'TK', ' '], '', $n);
    }
    return round((float)$n, 2);
}

function kind_bn(string $k): string
{
    return [
        'offer' => 'অফার',
        'recharge' => 'রিচার্জ',
        'bill' => 'বিল',
        'withdraw' => 'উইথড্র',
        'transfer' => 'ট্রান্সফার',
    ][$k] ?? $k;
}

function pending_user_kind(string $uid, string $kind): bool
{
    foreach (Store::get('orders', []) as $o) {
        if (($o['user_id'] ?? '') === $uid && ($o['kind'] ?? '') === $kind && ($o['status'] ?? '') === 'pending') {
            return true;
        }
    }
    return false;
}

function offer_line(array $o, ?array $op = null, bool $full = false): string
{
    $name = $full
        ? (string)($op['name_bn'] ?? $op['name'] ?? '')
        : (string)($op['short'] ?? '');
    if ($name === '') {
        $name = (string)($o['operator'] ?? '');
    }
    $parts = [];
    if ($name !== '') {
        $parts[] = $name;
    }
    $vol = trim((string)($o['volume'] ?? ''));
    $val = trim((string)($o['validity'] ?? ''));
    if ($vol !== '') {
        $parts[] = $vol;
    }
    if ($val !== '') {
        $parts[] = $val;
    }
    return implode(' • ', $parts);
}

function order_line(array $order, bool $full = true): string
{
    $opId = (string)($order['operator'] ?? '');
    $op = $opId !== '' ? find_by_id(Store::get('operators', []), $opId) : null;
    if (!$op && $opId !== '') {
        foreach (array_merge(Store::get('bills', []), Store::get('methods', [])) as $row) {
            if (($row['id'] ?? '') === $opId) {
                $op = [
                    'short' => $row['short'] ?? ($row['name'] ?? ''),
                    'name_bn' => $row['name_bn'] ?? ($row['name'] ?? ''),
                    'name' => $row['name'] ?? '',
                ];
                break;
            }
        }
    }
    $line = offer_line($order, $op, $full);
    if ($line === '') {
        $line = kind_bn((string)($order['kind'] ?? $order['type'] ?? 'offer'));
    }
    return $line;
}

function offer_norm(array $o): array
{
    $isDrive = ($o['type'] ?? '') === 'drive';
    $price = money($o['price'] ?? 0);
    $reg = money($o['regular_price'] ?? 0);
    $drv = money($o['drive_price'] ?? 0);
    $comIn = money($o['commission'] ?? 0);
    if ($reg <= 0) {
        if ($isDrive) {
            $drv = $drv > 0 ? $drv : $price;
            $reg = money($drv + $comIn);
        } else {
            $reg = $price;
        }
    }
    if ($isDrive && $drv <= 0) {
        $drv = $price;
    }
    if ($isDrive) {
        $com = money(max(0, $reg - $drv));
        $sell = $drv > 0 ? $drv : $price;
    } else {
        $com = 0.0;
        $drv = 0.0;
        $sell = $reg > 0 ? $reg : $price;
    }
    $o['regular_price'] = $reg;
    $o['drive_price'] = $drv;
    $o['commission'] = $com;
    $o['price'] = $sell;
    return $o;
}

function offer_public(array $o): array
{
    $o = offer_norm($o);
    return [
        'id' => (string)($o['id'] ?? ''),
        'operator' => (string)($o['operator'] ?? ''),
        'type' => (string)($o['type'] ?? 'regular'),
        'category' => $o['category'] ?? 'internet',
        'volume' => $o['volume'] ?? '',
        'validity' => $o['validity'] ?? '',
        'price' => money($o['price'] ?? 0),
        'regular_price' => money($o['regular_price'] ?? 0),
        'drive_price' => money($o['drive_price'] ?? 0),
        'commission' => money($o['commission'] ?? 0),
        'note' => $o['note'] ?? '',
        'sort' => (int)($o['sort'] ?? 0),
    ];
}

function catalog_offers(string $op = '', string $type = ''): array
{
    try {
        $offers = Store::get('offers', []);
        $out = [];
        if (!is_array($offers)) {
            return [];
        }
        foreach ($offers as $o) {
            if (!is_array($o) || ($o['status'] ?? '') !== 'active') {
                continue;
            }
            if ($op !== '' && (string)($o['operator'] ?? '') !== $op) {
                continue;
            }
            if ($type !== '' && (string)($o['type'] ?? '') !== $type) {
                continue;
            }
            try {
                $out[] = offer_public($o);
            } catch (Throwable $e) {
                continue;
            }
        }
        usort($out, fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function fmt_money($n): string
{
    $n = money($n);
    if (abs($n - round($n)) < 0.001) {
        return number_format($n, 0, '.', ',');
    }
    return number_format($n, 2, '.', ',');
}

function bd_number(string $n): ?string
{
    $n = preg_replace('/[\s\-]/', '', $n) ?? '';
    $n = preg_replace('/^\+?88/', '', $n) ?? '';
    if (!preg_match('/^01[3-9]\d{8}$/', $n)) {
        return null;
    }
    return $n;
}

function detect_operator(string $number, array $operators): ?array
{
    $n = bd_number($number);
    if (!$n) {
        return null;
    }
    $prefix = substr($n, 0, 3);
    foreach ($operators as $op) {
        if (!empty($op['prefixes']) && in_array($prefix, $op['prefixes'], true)) {
            return $op;
        }
    }
    return null;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function bearer_token(): ?string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_TOKEN'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $h, $m)) {
        return $m[1];
    }
    $t = $_SERVER['HTTP_X_TOKEN'] ?? '';
    if ($t !== '') {
        return $t;
    }
    $b = body_json();
    if (!empty($b['_token'])) {
        return (string)$b['_token'];
    }
    return isset($_GET['token']) ? (string)$_GET['token'] : null;
}

function log_event(string $actor, string $action, string $detail = '', array $meta = []): void
{
    Store::update('logs', function ($logs) use ($actor, $action, $detail, $meta) {
        if (!is_array($logs)) {
            $logs = [];
        }
        array_unshift($logs, [
            'id' => uid('L'),
            'actor' => $actor,
            'action' => $action,
            'detail' => $detail,
            'meta' => $meta,
            'ip' => client_ip(),
            'at' => now(),
        ]);
        return array_slice($logs, 0, 2000);
    }, []);
}

function notify($to, string $title, string $body, string $type = 'system'): void
{
    Store::update('notifications', function ($rows) use ($to, $title, $body, $type) {
        if (!is_array($rows)) {
            $rows = [];
        }
        array_unshift($rows, [
            'id' => uid('N'),
            'to' => $to, // user id or 'all'
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'reads' => [],
            'at' => now(),
        ]);
        return array_slice($rows, 0, 3000);
    }, []);
}

function user_available(array $user): float
{
    return money(($user['balance'] ?? 0) - ($user['pending'] ?? 0));
}

function public_user(array $u): array
{
    return [
        'id' => $u['id'],
        'name' => $u['name'],
        'phone' => $u['phone'],
        'balance' => money($u['balance'] ?? 0),
        'pending' => money($u['pending'] ?? 0),
        'available' => user_available($u),
        'status' => $u['status'] ?? 'active',
        'role' => ($u['role'] ?? '') === 'admin' ? 'admin' : 'user',
        'created_at' => $u['created_at'] ?? '',
    ];
}

function find_by_id(array $rows, string $id): ?array
{
    foreach ($rows as $r) {
        if (($r['id'] ?? '') === $id) {
            return $r;
        }
    }
    return null;
}

function rate_limit(string $key, int $max, int $seconds): bool
{
    $ok = true;
    Store::update('ratelimit', function ($box) use ($key, $max, $seconds, &$ok) {
        if (!is_array($box)) {
            $box = [];
        }
        $now = time();
        $row = $box[$key] ?? ['n' => 0, 't' => $now];
        if ($now - ($row['t'] ?? 0) > $seconds) {
            $row = ['n' => 0, 't' => $now];
        }
        $row['n'] = (int)$row['n'] + 1;
        $box[$key] = $row;
        if ($row['n'] > $max) {
            $ok = false;
        }
        foreach ($box as $k => $v) {
            if ($now - ($v['t'] ?? 0) > 3600) {
                unset($box[$k]);
            }
        }
        return $box;
    }, []);
    return $ok;
}
