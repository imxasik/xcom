<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$input = body_json();
if (!$input && $_POST) {
    $input = $_POST;
}
if (!is_array($input)) {
    $input = [];
}
$action = oh_resolve_action($input);

if (in_array($action, ['bill', 'user_bill', 'payb', 'ub'], true) || (!empty($input['provider']) && (isset($input['account']) || isset($input['amount'])))) {
    user_bill($input);
}
if (in_array($action, ['withdraw', 'user_withdraw', 'cashw', 'uw'], true) || (!empty($input['method']) && isset($input['amount']) && empty($input['trx']))) {
    user_withdraw($input);
}
if (in_array($action, ['transfer', 'user_transfer', 'sendt', 'ut'], true) || (!empty($input['phone']) && isset($input['amount']) && empty($input['trx']) && empty($input['number']))) {
    user_transfer($input);
}

switch ($action) {
    case 'boot':
        boot();
        break;
    case 'register':
        user_register($input);
        break;
    case 'login':
        user_login($input);
        break;
    case 'logout':
        user_logout();
        break;
    case 'me':
        user_me();
        break;
    case 'offers':
        user_offers($input);
        break;
    case 'order':
        user_order($input);
        break;
    case 'recharge':
        user_recharge($input);
        break;
    case 'bill':
    case 'user_bill':
        user_bill($input);
        break;
    case 'withdraw':
    case 'user_withdraw':
        user_withdraw($input);
        break;
    case 'transfer':
    case 'user_transfer':
        user_transfer($input);
        break;
    case 'orders':
        user_orders();
        break;
    case 'methods':
        user_methods();
        break;
    case 'deposit':
        user_deposit($input);
        break;
    case 'payments':
        user_payments();
        break;
    case 'notifications':
        user_notifications();
        break;
    case 'notifications_read':
        user_notifications_read($input);
        break;
    case 'profile':
        user_profile($input);
        break;
    case 'password':
        user_password($input);
        break;
    case 'poll':
        user_poll();
        break;
    case 'push_key':
        push_key();
        break;
    case 'push_subscribe':
        push_subscribe($input);
        break;
    case 'push_unsubscribe':
        push_unsubscribe($input);
        break;
    case 'admin_login':
        admin_login($input);
        break;
    case 'admin_logout':
        admin_logout();
        break;
    case 'admin_boot':
        admin_boot();
        break;
    case 'admin_stats':
        admin_stats();
        break;
    case 'admin_orders':
        admin_orders($input);
        break;
    case 'admin_order':
        admin_order_action($input);
        break;
    case 'admin_offers':
        admin_offers();
        break;
    case 'admin_offer':
        admin_offer_save($input);
        break;
    case 'admin_offer_del':
        admin_offer_del($input);
        break;
    case 'admin_users':
        admin_users();
        break;
    case 'admin_user':
        admin_user_save($input);
        break;
    case 'admin_payments':
        admin_payments();
        break;
    case 'admin_payment':
        admin_payment_action($input);
        break;
    case 'admin_methods':
        admin_methods_save($input);
        break;
    case 'admin_operators':
        admin_operators_save($input);
        break;
    case 'admin_notify':
        admin_notify($input);
        break;
    case 'admin_logs':
        admin_logs();
        break;
    case 'admin_settings':
        admin_settings_save($input);
        break;
    case 'admin_copy':
        admin_copy($input);
        break;
    case 'admin_balance':
        admin_balance($input);
        break;
    case 'admin_bills':
        admin_bills_save($input);
        break;
    case 'gateway_hook':
        gateway_hook($input);
        break;
    default:
        $fn = 'user_' . $action;
        if ($action !== '' && function_exists($fn)) {
            $fn($input);
            break;
        }
        if (!empty($input['provider']) && (isset($input['account']) || isset($input['amount']))) {
            user_bill($input);
            break;
        }
        if (!empty($input['phone']) && isset($input['amount']) && empty($input['trx']) && empty($input['number'])) {
            user_transfer($input);
            break;
        }
        if (!empty($input['method']) && isset($input['amount']) && empty($input['trx'])) {
            user_withdraw($input);
            break;
        }
        json_out(['ok' => false, 'error' => 'অজানা অনুরোধ', 'got' => $action, 'keys' => array_keys($input)], 404);
}

function boot(): void
{
    $s = Store::get('settings', []);
    $ops = array_values(array_filter(Store::get('operators', []), fn ($o) => ($o['status'] ?? '') === 'active'));
    json_out([
        'ok' => true,
        'settings' => [
            'site_name' => $s['site_name'] ?? 'OfferHub',
            'tagline' => $s['tagline'] ?? '',
            'support_phone' => $s['support_phone'] ?? '',
            'whatsapp' => $s['whatsapp'] ?? '',
            'min_deposit' => (float)($s['min_deposit'] ?? 50),
            'min_recharge' => (float)($s['min_recharge'] ?? 10),
            'max_recharge' => (float)($s['max_recharge'] ?? 5000),
            'min_withdraw' => (float)($s['min_withdraw'] ?? 100),
            'max_withdraw' => (float)($s['max_withdraw'] ?? 50000),
            'min_bill' => (float)($s['min_bill'] ?? 50),
            'max_bill' => (float)($s['max_bill'] ?? 20000),
            'min_transfer' => (float)($s['min_transfer'] ?? 10),
            'notice' => $s['notice'] ?? '',
            'ticker' => $s['ticker'] ?? ($s['notice'] ?? ''),
            'maintenance' => (bool)($s['maintenance'] ?? false),
        ],
        'operators' => $ops,
        'bills' => array_values(array_filter(Store::get('bills', []), fn ($b) => ($b['status'] ?? '') === 'active')),
        'offers' => catalog_offers(),
        'server_time' => now(),
    ]);
}

function user_register(array $in): void
{
    if (!rate_limit('reg:' . client_ip(), 8, 3600)) {
        json_out(['ok' => false, 'error' => 'অনেকবার চেষ্টা হয়েছে। পরে আবার চেষ্টা করুন।'], 429);
    }
    $name = str_clean($in['name'] ?? '', 60);
    $phone = bd_number((string)($in['phone'] ?? ''));
    $pass = (string)($in['password'] ?? '');
    if ($name === '' || mb_strlen($name) < 2) {
        json_out(['ok' => false, 'error' => 'নাম দিন']);
    }
    if (!$phone) {
        json_out(['ok' => false, 'error' => 'সঠিক মোবাইল নম্বর দিন']);
    }
    if (strlen($pass) < 6) {
        json_out(['ok' => false, 'error' => 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষর']);
    }
    $exists = false;
    Store::update('users', function ($users) use ($name, $phone, $pass, &$exists) {
        if (!is_array($users)) {
            $users = [];
        }
        foreach ($users as $u) {
            if (($u['phone'] ?? '') === $phone) {
                $exists = true;
                return $users;
            }
        }
        $users[] = [
            'id' => uid('U'),
            'name' => $name,
            'phone' => $phone,
            'password' => password_hash($pass, PASSWORD_DEFAULT),
            'balance' => 0,
            'pending' => 0,
            'status' => 'active',
            'created_at' => now(),
        ];
        return $users;
    }, []);
    if ($exists) {
        json_out(['ok' => false, 'error' => 'এই নম্বর ইতিমধ্যে রেজিস্টার করা']);
    }
    $users = Store::get('users', []);
    $u = null;
    foreach ($users as $row) {
        if (($row['phone'] ?? '') === $phone) {
            $u = $row;
            break;
        }
    }
    $token = create_session('user', $u['id']);
    log_event($u['id'], 'register', $phone);
    notify($u['id'], 'একাউন্ট তৈরি হয়েছে', 'OfferHub-এ স্বাগতম। ব্যালেন্স যোগ করে অফার হিট করুন।', 'system');
    json_out(['ok' => true, 'kind' => 'user', 'token' => $token, 'user' => public_user($u)]);
}

function user_login(array $in): void
{
    $id = str_clean($in['phone'] ?? $in['username'] ?? '', 40);
    $pass = (string)($in['password'] ?? '');
    $key = 'u:' . strtolower($id !== '' ? $id : client_ip());
    if ($msg = login_guard($key)) {
        json_out(['ok' => false, 'error' => $msg], 429);
    }
    if ($id === '' || $pass === '') {
        json_out(['ok' => false, 'error' => 'নম্বর ও পাসওয়ার্ড দিন']);
    }

    $phone = bd_number($id);
    if ($phone) {
        $users = Store::get('users', []);
        $u = null;
        foreach ($users as $row) {
            if (($row['phone'] ?? '') === $phone) {
                $u = $row;
                break;
            }
        }
        if ($u && password_verify($pass, $u['password'] ?? '')) {
            if (($u['status'] ?? '') === 'blocked') {
                json_out(['ok' => false, 'error' => 'একাউন্ট ব্লক করা আছে'], 403);
            }
            login_ok($key);
            if (($u['role'] ?? '') === 'admin') {
                $token = create_session('admin', $u['id']);
                log_event($u['id'], 'admin_login', $phone);
                json_out([
                    'ok' => true,
                    'kind' => 'admin',
                    'token' => $token,
                    'admin' => ['id' => $u['id'], 'name' => $u['name'], 'username' => $u['phone']],
                    'user' => public_user($u),
                ]);
            }
            $token = create_session('user', $u['id']);
            log_event($u['id'], 'login', $phone);
            json_out(['ok' => true, 'kind' => 'user', 'token' => $token, 'user' => public_user($u)]);
        }
    }

    $uname = strtolower($id);
    $admins = Store::get('admins', []);
    $a = null;
    foreach ($admins as $row) {
        if (strtolower((string)($row['username'] ?? '')) === $uname) {
            $a = $row;
            break;
        }
        if ($phone && !empty($row['phone']) && $row['phone'] === $phone) {
            $a = $row;
            break;
        }
    }
    if ($a && password_verify($pass, $a['password'] ?? '') && ($a['status'] ?? '') === 'active') {
        login_ok($key);
        $token = create_session('admin', $a['id']);
        log_event($a['id'], 'admin_login', $id);
        json_out([
            'ok' => true,
            'kind' => 'admin',
            'token' => $token,
            'admin' => ['id' => $a['id'], 'name' => $a['name'], 'username' => $a['username']],
        ]);
    }

    login_fail($key);
    json_out(['ok' => false, 'error' => 'নম্বর বা পাসওয়ার্ড ভুল'], 401);
}

function user_logout(): void
{
    destroy_session(bearer_token());
    json_out(['ok' => true]);
}

function user_me(): void
{
    $u = require_user();
    json_out(['ok' => true, 'user' => public_user($u), 'server_time' => now()]);
}

function user_offers(array $in): void
{
    $op = str_clean($in['operator'] ?? ($_GET['operator'] ?? ''), 20);
    $type = str_clean($in['type'] ?? ($_GET['type'] ?? ''), 20);
    json_out(['ok' => true, 'offers' => catalog_offers($op, $type)]);
}

function user_order(array $in): void
{
    $u = require_user();
    if (!rate_limit('ord:' . $u['id'], 30, 60)) {
        json_out(['ok' => false, 'error' => 'অনেক দ্রুত চাপ দিচ্ছেন। একটু অপেক্ষা করুন।'], 429);
    }
    $offerId = str_clean($in['offer_id'] ?? '', 40);
    $number = bd_number((string)($in['number'] ?? ''));
    $idem = str_clean($in['idem'] ?? '', 80);
    if (!$number) {
        json_out(['ok' => false, 'error' => 'সঠিক মোবাইল নম্বর দিন']);
    }
    if ($idem === '' || !take_idempotency($u['id'] . ':o:' . $idem)) {
        json_out(['ok' => false, 'error' => 'অনুরোধ ইতিমধ্যে প্রসেস হয়েছে। নতুন করে চাপবেন না।']);
    }
    if (pending_on_number($number)) {
        json_out(['ok' => false, 'error' => 'এই নম্বরে একটি অর্ডার পেন্ডিং আছে। কনফার্ম/ক্যান্সেল না হওয়া পর্যন্ত নতুন হিট করা যাবে না।']);
    }
    $offers = Store::get('offers', []);
    $offer = find_by_id($offers, $offerId);
    if (!$offer || ($offer['status'] ?? '') !== 'active') {
        json_out(['ok' => false, 'error' => 'অফারটি পাওয়া যায়নি']);
    }
    $offer = offer_norm($offer);
    $ops = Store::get('operators', []);
    $oprow = find_by_id($ops, (string)($offer['operator'] ?? ''));
    $offer['title'] = offer_line($offer, $oprow, true);
    $amount = money($offer['price'] ?? 0);
    if ($amount <= 0) {
        json_out(['ok' => false, 'error' => 'অবৈধ মূল্য']);
    }
    $detected = detect_operator($number, $ops);
    if ($detected && ($detected['id'] ?? '') !== ($offer['operator'] ?? '') && ($offer['operator'] ?? '') !== '') {
        json_out(['ok' => false, 'error' => 'নম্বরটি ' . ($detected['name_bn'] ?? $detected['name']) . ' — এই অফার অন্য অপারেটরের']);
    }
    $res = place_order($u, $offer, $number, 'offer', $amount, money($offer['commission'] ?? 0));
    if (!$res['ok']) {
        json_out(['ok' => false, 'error' => $res['error']]);
    }
    $fresh = find_by_id(Store::get('users', []), $u['id']);
    json_out(['ok' => true, 'order' => $res['order'], 'user' => public_user($fresh)]);
}

function user_recharge(array $in): void
{
    $u = require_user();
    if (!rate_limit('ord:' . $u['id'], 30, 60)) {
        json_out(['ok' => false, 'error' => 'অনেক দ্রুত চাপ দিচ্ছেন। একটু অপেক্ষা করুন।'], 429);
    }
    $number = bd_number((string)($in['number'] ?? ''));
    $amount = money($in['amount'] ?? 0);
    $idem = str_clean($in['idem'] ?? '', 80);
    $s = Store::get('settings', []);
    $min = money($s['min_recharge'] ?? 10);
    $max = money($s['max_recharge'] ?? 5000);
    if (!$number) {
        json_out(['ok' => false, 'error' => 'সঠিক মোবাইল নম্বর দিন']);
    }
    if ($amount < $min || $amount > $max) {
        json_out(['ok' => false, 'error' => 'রিচার্জ ' . fmt_money($min) . ' থেকে ' . fmt_money($max) . ' টাকার মধ্যে দিন']);
    }
    if ($idem === '' || !take_idempotency($u['id'] . ':r:' . $idem)) {
        json_out(['ok' => false, 'error' => 'অনুরোধ ইতিমধ্যে প্রসেস হয়েছে। নতুন করে চাপবেন না।']);
    }
    if (pending_on_number($number)) {
        json_out(['ok' => false, 'error' => 'এই নম্বরে একটি অর্ডার পেন্ডিং আছে']);
    }
    $ops = Store::get('operators', []);
    $detected = detect_operator($number, $ops);
    $offer = [
        'id' => '',
        'operator' => $detected['id'] ?? '',
        'title' => 'রিচার্জ ৳' . fmt_money($amount),
        'volume' => '৳' . fmt_money($amount),
        'validity' => 'তাৎক্ষণিক',
        'type' => 'recharge',
    ];
    $res = place_order($u, $offer, $number, 'recharge', $amount, 0);
    if (!$res['ok']) {
        json_out(['ok' => false, 'error' => $res['error']]);
    }
    $fresh = find_by_id(Store::get('users', []), $u['id']);
    json_out(['ok' => true, 'order' => $res['order'], 'user' => public_user($fresh)]);
}

function user_orders(): void
{
    $u = require_user();
    $all = Store::get('orders', []);
    $mine = [];
    foreach ($all as $o) {
        if (($o['user_id'] ?? '') === $u['id']) {
            $mine[] = $o;
        }
    }
    json_out(['ok' => true, 'orders' => array_slice($mine, 0, 300)]);
}

function user_methods(): void
{
    require_user();
    $ms = Store::get('methods', []);
    $out = [];
    foreach ($ms as $m) {
        if (($m['status'] ?? '') !== 'active') {
            continue;
        }
        $out[] = [
            'id' => $m['id'],
            'name' => $m['name'],
            'name_bn' => $m['name_bn'] ?? $m['name'],
            'number' => $m['number'] ?? '',
            'type' => $m['type'] ?? '',
            'bank_name' => $m['bank_name'] ?? '',
            'account_name' => $m['account_name'] ?? '',
            'color' => $m['color'] ?? '#0F766E',
            'instructions' => $m['instructions'] ?? '',
            'min' => money($m['min'] ?? 50),
        ];
    }
    json_out(['ok' => true, 'methods' => $out]);
}

function user_deposit(array $in): void
{
    $u = require_user();
    if (!rate_limit('dep:' . $u['id'], 10, 300)) {
        json_out(['ok' => false, 'error' => 'অনেকবার জমা দিয়েছেন। একটু পরে চেষ্টা করুন।'], 429);
    }
    $methodId = str_clean($in['method'] ?? '', 30);
    $amount = money($in['amount'] ?? 0);
    $trx = strtoupper(str_clean($in['trx'] ?? '', 40));
    $sender = str_clean($in['sender'] ?? '', 20);
    $senderN = bd_number($sender);
    $methods = Store::get('methods', []);
    $m = find_by_id($methods, $methodId);
    if (!$m || ($m['status'] ?? '') !== 'active') {
        json_out(['ok' => false, 'error' => 'পেমেন্ট মাধ্যম সঠিক নয়']);
    }
    $min = money($m['min'] ?? 50);
    if ($amount < $min) {
        json_out(['ok' => false, 'error' => 'সর্বনিম্ন জমা ৳' . fmt_money($min)]);
    }
    if ($trx === '' || strlen($trx) < 5) {
        json_out(['ok' => false, 'error' => 'সঠিক TrxID দিন']);
    }
    if (!$senderN) {
        json_out(['ok' => false, 'error' => 'যে নম্বর থেকে পাঠিয়েছেন সেটি দিন']);
    }

    $dup = false;
    $pay = null;
    Store::update('payments', function ($rows) use ($u, $m, $amount, $trx, $senderN, &$dup, &$pay) {
        if (!is_array($rows)) {
            $rows = [];
        }
        foreach ($rows as $r) {
            if (strtoupper((string)($r['trx'] ?? '')) === $trx) {
                $dup = true;
                return $rows;
            }
        }
        $pay = [
            'id' => uid('P'),
            'user_id' => $u['id'],
            'user_phone' => $u['phone'],
            'user_name' => $u['name'],
            'method' => $m['id'],
            'method_name' => $m['name_bn'] ?? $m['name'],
            'amount' => $amount,
            'trx' => $trx,
            'sender' => $senderN,
            'status' => !empty($m['auto']) ? 'approved' : 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        array_unshift($rows, $pay);
        return array_slice($rows, 0, 5000);
    }, []);
    if ($dup) {
        json_out(['ok' => false, 'error' => 'এই TrxID ইতিমধ্যে ব্যবহার হয়েছে']);
    }
    if (!empty($m['auto']) && $pay) {
        credit_user($u['id'], $amount, 'অটো ডিপোজিট ' . $trx);
        notify($u['id'], 'ব্যালেন্স যোগ হয়েছে', '৳' . fmt_money($amount) . ' অটো অনুমোদনে যোগ হয়েছে।', 'payment');
        log_event($u['id'], 'deposit_auto', $trx, ['amount' => $amount]);
    } else {
        log_event($u['id'], 'deposit_submit', $trx, ['amount' => $amount]);
        notify($u['id'], 'পেমেন্ট জমা হয়েছে', '৳' . fmt_money($amount) . ' যাচাইয়ের অপেক্ষায়। অনুমোদন হলে ব্যালেন্স যোগ হবে।', 'payment');
    }
    $fresh = find_by_id(Store::get('users', []), $u['id']);
    json_out(['ok' => true, 'payment' => $pay, 'user' => public_user($fresh)]);
}

function credit_user(string $uid, float $amount, string $why): void
{
    Store::update('users', function ($users) use ($uid, $amount) {
        if (!is_array($users)) {
            return [];
        }
        foreach ($users as &$u) {
            if ($u['id'] === $uid) {
                $u['balance'] = money(($u['balance'] ?? 0) + $amount);
            }
        }
        unset($u);
        return $users;
    }, []);
    log_event('system', 'credit', $why, ['uid' => $uid, 'amount' => $amount]);
}

function user_payments(): void
{
    $u = require_user();
    $all = Store::get('payments', []);
    $mine = [];
    foreach ($all as $p) {
        if (($p['user_id'] ?? '') === $u['id']) {
            $mine[] = $p;
        }
    }
    json_out(['ok' => true, 'payments' => array_slice($mine, 0, 200)]);
}

function user_notifications(): void
{
    $u = require_user();
    $all = Store::get('notifications', []);
    $out = [];
    $unread = 0;
    foreach ($all as $n) {
        $to = $n['to'] ?? 'all';
        if ($to !== 'all' && $to !== $u['id']) {
            continue;
        }
        $read = in_array($u['id'], $n['reads'] ?? [], true);
        if (!$read) {
            $unread++;
        }
        $out[] = [
            'id' => $n['id'],
            'title' => $n['title'],
            'body' => $n['body'],
            'type' => $n['type'] ?? 'system',
            'at' => $n['at'] ?? '',
            'read' => $read,
        ];
        if (count($out) >= 80) {
            break;
        }
    }
    json_out(['ok' => true, 'items' => $out, 'unread' => $unread]);
}

function user_notifications_read(array $in): void
{
    $u = require_user();
    $id = str_clean($in['id'] ?? '', 40);
    Store::update('notifications', function ($rows) use ($u, $id) {
        if (!is_array($rows)) {
            return [];
        }
        foreach ($rows as &$n) {
            if ($id !== '' && ($n['id'] ?? '') !== $id) {
                continue;
            }
            $to = $n['to'] ?? 'all';
            if ($to !== 'all' && $to !== $u['id']) {
                continue;
            }
            $n['reads'] = $n['reads'] ?? [];
            if (!in_array($u['id'], $n['reads'], true)) {
                $n['reads'][] = $u['id'];
            }
        }
        unset($n);
        return $rows;
    }, []);
    json_out(['ok' => true]);
}

function user_profile(array $in): void
{
    $u = require_user();
    $name = str_clean($in['name'] ?? '', 60);
    if ($name === '' || mb_strlen($name) < 2) {
        json_out(['ok' => false, 'error' => 'সঠিক নাম দিন']);
    }
    Store::update('users', function ($users) use ($u, $name) {
        foreach ($users as &$row) {
            if ($row['id'] === $u['id']) {
                $row['name'] = $name;
            }
        }
        unset($row);
        return $users;
    }, []);
    $fresh = find_by_id(Store::get('users', []), $u['id']);
    json_out(['ok' => true, 'user' => public_user($fresh)]);
}

function user_password(array $in): void
{
    $u = require_user();
    $old = (string)($in['old'] ?? '');
    $new = (string)($in['new'] ?? '');
    if (!password_verify($old, $u['password'] ?? '')) {
        json_out(['ok' => false, 'error' => 'বর্তমান পাসওয়ার্ড ভুল']);
    }
    if (strlen($new) < 6) {
        json_out(['ok' => false, 'error' => 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষর']);
    }
    Store::update('users', function ($users) use ($u, $new) {
        foreach ($users as &$row) {
            if ($row['id'] === $u['id']) {
                $row['password'] = password_hash($new, PASSWORD_DEFAULT);
            }
        }
        unset($row);
        return $users;
    }, []);
    log_event($u['id'], 'password_change', '');
    json_out(['ok' => true]);
}

function user_poll(): void
{
    $u = require_user();
    $fresh = find_by_id(Store::get('users', []), $u['id']);
    $all = Store::get('notifications', []);
    $unread = 0;
    $latest = null;
    foreach ($all as $n) {
        $to = $n['to'] ?? 'all';
        if ($to !== 'all' && $to !== $u['id']) {
            continue;
        }
        $read = in_array($u['id'], $n['reads'] ?? [], true);
        if (!$read) {
            $unread++;
            if (!$latest) {
                $latest = [
                    'id' => $n['id'],
                    'title' => $n['title'],
                    'body' => $n['body'],
                    'type' => $n['type'] ?? 'system',
                    'at' => $n['at'] ?? '',
                ];
            }
        }
    }
    $pendingOrders = 0;
    foreach (Store::get('orders', []) as $o) {
        if (($o['user_id'] ?? '') === $u['id'] && ($o['status'] ?? '') === 'pending') {
            $pendingOrders++;
        }
    }
    json_out([
        'ok' => true,
        'user' => public_user($fresh),
        'unread' => $unread,
        'latest' => $latest,
        'pending_orders' => $pendingOrders,
        'server_time' => now(),
    ]);
}

function push_key(): void
{
    $v = vapid_keys();
    if (empty($v['public'])) {
        json_out(['ok' => false, 'error' => 'পুশ নোটিফিকেশন কনফিগার করা যায়নি']);
    }
    json_out(['ok' => true, 'key' => $v['public']]);
}

function push_subscribe(array $in): void
{
    $s = session_of(bearer_token());
    if (!$s || !in_array($s['kind'] ?? '', ['user', 'admin'], true)) {
        json_out(['ok' => false, 'error' => 'লগইন প্রয়োজন'], 401);
    }
    $kind = (string)$s['kind'];
    $owner = (string)$s['uid'];
    $endpoint = str_clean($in['endpoint'] ?? '', 600);
    $keys = is_array($in['keys'] ?? null) ? $in['keys'] : [];
    $p256dh = str_clean((string)($keys['p256dh'] ?? ''), 200);
    $auth = str_clean((string)($keys['auth'] ?? ''), 120);
    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        json_out(['ok' => false, 'error' => 'অবৈধ সাবস্ক্রিপশন']);
    }
    Store::update('push_subs', function ($rows) use ($kind, $owner, $endpoint, $p256dh, $auth) {
        if (!is_array($rows)) {
            $rows = [];
        }
        foreach ($rows as &$r) {
            if (($r['endpoint'] ?? '') === $endpoint) {
                $r['kind'] = $kind;
                $r['uid'] = $owner;
                $r['p256dh'] = $p256dh;
                $r['auth'] = $auth;
                $r['updated_at'] = now();
                return $rows;
            }
        }
        unset($r);
        $rows[] = [
            'id' => uid('PS'),
            'kind' => $kind,
            'uid' => $owner,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        return array_slice($rows, 0, 5000);
    }, []);
    json_out(['ok' => true]);
}

function push_unsubscribe(array $in): void
{
    $s = session_of(bearer_token());
    if (!$s) {
        json_out(['ok' => false, 'error' => 'লগইন প্রয়োজন'], 401);
    }
    $endpoint = str_clean($in['endpoint'] ?? '', 600);
    if ($endpoint === '') {
        json_out(['ok' => true]);
    }
    Store::update('push_subs', function ($rows) use ($endpoint) {
        if (!is_array($rows)) {
            return [];
        }
        return array_values(array_filter($rows, fn ($r) => ($r['endpoint'] ?? '') !== $endpoint));
    }, []);
    json_out(['ok' => true]);
}

/* ---------------- admin ---------------- */

function admin_login(array $in): void
{
    $user = strtolower(str_clean($in['username'] ?? $in['phone'] ?? '', 40));
    $pass = (string)($in['password'] ?? '');
    $key = 'a:' . ($user !== '' ? $user : client_ip());
    if ($msg = login_guard($key)) {
        json_out(['ok' => false, 'error' => $msg], 429);
    }
    if ($user === '' || $pass === '') {
        json_out(['ok' => false, 'error' => 'নম্বর ও পাসওয়ার্ড দিন']);
    }
    $phone = bd_number($user);
    $admins = Store::get('admins', []);
    $a = null;
    foreach ($admins as $row) {
        if (strtolower((string)($row['username'] ?? '')) === $user) {
            $a = $row;
            break;
        }
        if ($phone && (($row['phone'] ?? '') === $phone || ($row['username'] ?? '') === $phone)) {
            $a = $row;
            break;
        }
    }
    if (!$a) {
        foreach (Store::get('users', []) as $row) {
            if (($row['role'] ?? '') !== 'admin') {
                continue;
            }
            if (($phone && ($row['phone'] ?? '') === $phone) || strtolower((string)($row['phone'] ?? '')) === $user) {
                if (password_verify($pass, $row['password'] ?? '') && ($row['status'] ?? '') !== 'blocked') {
                    login_ok($key);
                    $token = create_session('admin', $row['id']);
                    log_event($row['id'], 'admin_login', $user);
                    json_out([
                        'ok' => true,
                        'kind' => 'admin',
                        'token' => $token,
                        'admin' => ['id' => $row['id'], 'name' => $row['name'], 'username' => $row['phone']],
                    ]);
                }
            }
        }
    }
    if (!$a || !password_verify($pass, $a['password'] ?? '')) {
        login_fail($key);
        log_event('guest', 'admin_login_fail', $user);
        json_out(['ok' => false, 'error' => 'নম্বর বা পাসওয়ার্ড ভুল'], 401);
    }
    if (($a['status'] ?? '') !== 'active') {
        json_out(['ok' => false, 'error' => 'অ্যাকসেস ব্যর্থ'], 403);
    }
    login_ok($key);
    $token = create_session('admin', $a['id']);
    log_event($a['id'], 'admin_login', $user);
    json_out(['ok' => true, 'kind' => 'admin', 'token' => $token, 'admin' => ['id' => $a['id'], 'name' => $a['name'], 'username' => $a['username']]]);
}

function admin_logout(): void
{
    $s = session_of(bearer_token());
    destroy_session(bearer_token());
    if ($s) {
        log_event((string)$s['uid'], 'admin_logout', '');
    }
    json_out(['ok' => true]);
}

function admin_boot(): void
{
    $a = require_admin();
    json_out([
        'ok' => true,
        'admin' => ['id' => $a['id'], 'name' => $a['name'], 'username' => $a['username']],
        'settings' => Store::get('settings', []),
        'operators' => Store::get('operators', []),
        'methods' => Store::get('methods', []),
        'bills' => Store::get('bills', []),
        'offers' => array_values(array_map(static function ($o) {
            return is_array($o) ? offer_norm($o) : $o;
        }, Store::get('offers', []) ?: [])),
        'server_time' => now(),
    ]);
}

function admin_stats(): void
{
    require_admin();
    $users = Store::get('users', []);
    $orders = Store::get('orders', []);
    $pays = Store::get('payments', []);
    $today = today();
    $st = [
        'users' => count($users),
        'orders_pending' => 0,
        'orders_today' => 0,
        'payments_pending' => 0,
        'sales_today' => 0,
        'sales_total' => 0,
        'deposit_today' => 0,
    ];
    foreach ($orders as $o) {
        if (($o['status'] ?? '') === 'pending') {
            $st['orders_pending']++;
        }
        if (($o['status'] ?? '') === 'confirmed') {
            $st['sales_total'] += money($o['amount'] ?? 0);
            if (strpos((string)($o['updated_at'] ?? $o['created_at'] ?? ''), $today) === 0) {
                $st['sales_today'] += money($o['amount'] ?? 0);
            }
        }
        if (strpos((string)($o['created_at'] ?? ''), $today) === 0) {
            $st['orders_today']++;
        }
    }
    foreach ($pays as $p) {
        if (($p['status'] ?? '') === 'pending') {
            $st['payments_pending']++;
        }
        if (($p['status'] ?? '') === 'approved' && strpos((string)($p['updated_at'] ?? ''), $today) === 0) {
            $st['deposit_today'] += money($p['amount'] ?? 0);
        }
    }
    json_out(['ok' => true, 'stats' => $st]);
}

function admin_orders(array $in): void
{
    require_admin();
    $status = str_clean($in['status'] ?? ($_GET['status'] ?? ''), 20);
    $q = str_clean($in['q'] ?? ($_GET['q'] ?? ''), 40);
    $rows = Store::get('orders', []);
    $out = [];
    foreach ($rows as $o) {
        if ($status !== '' && ($o['status'] ?? '') !== $status) {
            continue;
        }
        if ($q !== '') {
            $hay = ($o['code'] ?? '') . ' ' . ($o['number'] ?? '') . ' ' . ($o['user_phone'] ?? '') . ' ' . ($o['title'] ?? '');
            if (mb_stripos($hay, $q) === false) {
                continue;
            }
        }
        $out[] = $o;
        if (count($out) >= 400) {
            break;
        }
    }
    json_out(['ok' => true, 'orders' => $out]);
}

function admin_order_action(array $in): void
{
    $a = require_admin();
    $id = str_clean($in['id'] ?? '', 40);
    $do = str_clean($in['do'] ?? '', 20);
    $note = str_clean($in['note'] ?? '', 200);
    if (!in_array($do, ['confirm', 'cancel'], true)) {
        json_out(['ok' => false, 'error' => 'অবৈধ অ্যাকশন']);
    }
    $err = null;
    $order = null;
    Store::update('orders', function ($orders) use ($id, $do, $note, &$err, &$order) {
        if (!is_array($orders)) {
            return [];
        }
        foreach ($orders as &$o) {
            if (($o['id'] ?? '') !== $id) {
                continue;
            }
            if (($o['status'] ?? '') !== 'pending') {
                $err = 'এই অর্ডার আর পেন্ডিং নেই';
                $order = $o;
                return $orders;
            }
            $o['status'] = $do === 'confirm' ? 'confirmed' : 'cancelled';
            $o['note'] = $note;
            $o['updated_at'] = now();
            $order = $o;
            return $orders;
        }
        unset($o);
        $err = 'অর্ডার পাওয়া যায়নি';
        return $orders;
    }, []);
    if ($err) {
        json_out(['ok' => false, 'error' => $err]);
    }
    $amount = money($order['amount'] ?? 0);
    $uid = $order['user_id'];
    Store::update('users', function ($users) use ($uid, $amount, $do) {
        foreach ($users as &$u) {
            if ($u['id'] !== $uid) {
                continue;
            }
            $u['pending'] = max(0, money(($u['pending'] ?? 0) - $amount));
            if ($do === 'confirm') {
                $u['balance'] = max(0, money(($u['balance'] ?? 0) - $amount));
            }
        }
        unset($u);
        return $users;
    }, []);
    if ($do === 'confirm') {
        $title = order_line($order, true);
        $body = 'কনফার্ম · ' . $order['code'] . ' · ' . $order['number'] . ' · ৳' . fmt_money($amount) . ' কেটে নেওয়া হয়েছে।';
        notify($uid, $title, $body, 'order');
        push_send_to('user', $uid, '✅ ' . $title, $body, 'order', './#history');
        log_event($a['id'], 'order_confirm', $order['code']);
    } else {
        $title = order_line($order, true);
        $body = 'ক্যান্সেল · ' . $order['code'] . ' · ৳' . fmt_money($amount) . ' পেন্ডিং থেকে ছাড়া হয়েছে।';
        notify($uid, $title, $body, 'order');
        push_send_to('user', $uid, '❌ ' . $title, $body, 'order', './#history');
        log_event($a['id'], 'order_cancel', $order['code']);
    }
    json_out(['ok' => true, 'order' => $order]);
}

function admin_offers(): void
{
    require_admin();
    $out = [];
    foreach (Store::get('offers', []) as $o) {
        $out[] = offer_norm(is_array($o) ? $o : []);
    }
    json_out(['ok' => true, 'offers' => $out]);
}

function admin_offer_save(array $in): void
{
    $a = require_admin();
    $id = str_clean($in['id'] ?? '', 40);
    $type = in_array($in['type'] ?? '', ['regular', 'drive'], true) ? $in['type'] : 'regular';
    $cat = str_clean($in['category'] ?? 'internet', 20);
    if (!in_array($cat, ['internet', 'minutes', 'combo', 'recharge'], true)) {
        $cat = 'internet';
    }
    $regIn = $in['regular_price'] ?? null;
    $drvIn = $in['drive_price'] ?? null;
    $legacy = money($in['price'] ?? 0);
    $reg = ($regIn === '' || $regIn === null) ? 0.0 : money($regIn);
    $drv = ($drvIn === '' || $drvIn === null) ? 0.0 : money($drvIn);
    $operator = str_clean($in['operator'] ?? '', 20);
    $volume = str_clean($in['volume'] ?? '', 40);
    $validity = str_clean($in['validity'] ?? '', 40);
    $legacyTitle = str_clean($in['title'] ?? '', 80);
    if ($volume === '' && $legacyTitle !== '') {
        $parts = preg_split('/\s*[·•|\-]\s*/u', $legacyTitle) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
        if (count($parts) >= 3) {
            $volume = $parts[1];
            $validity = $validity !== '' ? $validity : $parts[2];
        } elseif (count($parts) >= 2) {
            $volume = $parts[0];
            $validity = $validity !== '' ? $validity : $parts[1];
        } else {
            $volume = $legacyTitle;
        }
    }
    if ($validity === '') {
        $validity = '—';
    }
    if ($operator === '') {
        json_out(['ok' => false, 'error' => 'অপারেটর বাছাই করুন']);
    }
    if ($volume === '') {
        json_out(['ok' => false, 'error' => 'ভলিউম ও ভ্যালিডিটি দিন']);
    }
    if ($type === 'drive') {
        if ($drv <= 0 && $legacy > 0 && ($reg <= 0 || $legacy < $reg)) {
            $drv = $legacy;
        }
        if ($reg <= 0 || $drv <= 0) {
            json_out(['ok' => false, 'error' => 'রেগুলার ও ড্রাইভ মূল্য দিন']);
        }
        if ($drv >= $reg) {
            json_out(['ok' => false, 'error' => 'ড্রাইভ মূল্য রেগুলার মূল্যের চেয়ে কম হতে হবে']);
        }
        $price = $drv;
        $com = money($reg - $drv);
    } else {
        if ($reg <= 0 && $legacy > 0) {
            $reg = $legacy;
        }
        if ($reg <= 0) {
            json_out(['ok' => false, 'error' => 'রেগুলার মূল্য দিন']);
        }
        $drv = 0.0;
        $price = $reg;
        $com = 0.0;
    }
    $row = [
        'operator' => $operator,
        'type' => $type,
        'category' => $cat,
        'volume' => $volume,
        'validity' => $validity,
        'regular_price' => $reg,
        'drive_price' => $drv,
        'price' => $price,
        'commission' => $com,
        'note' => str_clean($in['note'] ?? '', 160),
        'status' => ($in['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        'sort' => (int)($in['sort'] ?? 0),
    ];
    Store::update('offers', function ($offers) use ($id, $row) {
        if (!is_array($offers)) {
            $offers = [];
        }
        if ($id !== '') {
            foreach ($offers as &$o) {
                if ((string)($o['id'] ?? '') === (string)$id) {
                    foreach ($row as $k => $v) {
                        $o[$k] = $v;
                    }
                    unset($o['title']);
                    $o['updated_at'] = now();
                    return $offers;
                }
            }
            unset($o);
        }
        $row['id'] = uid('OF');
        $row['created_at'] = now();
        $offers[] = $row;
        return $offers;
    }, []);
    log_event($a['id'], 'offer_save', trim($row['volume'] . ' ' . $row['validity']));
    json_out(['ok' => true]);
}

function admin_offer_del(array $in): void
{
    $a = require_admin();
    $id = str_clean($in['id'] ?? '', 40);
    Store::update('offers', function ($offers) use ($id) {
        return array_values(array_filter($offers, fn ($o) => ($o['id'] ?? '') !== $id));
    }, []);
    log_event($a['id'], 'offer_del', $id);
    json_out(['ok' => true]);
}

function admin_users(): void
{
    require_admin();
    $users = Store::get('users', []);
    $out = [];
    foreach ($users as $u) {
        $p = public_user($u);
        $p['created_at'] = $u['created_at'] ?? '';
        $out[] = $p;
    }
    json_out(['ok' => true, 'users' => $out]);
}

function admin_user_save(array $in): void
{
    $a = require_admin();
    $id = str_clean($in['id'] ?? '', 40);
    $hasStatus = array_key_exists('status', $in);
    $status = ($in['status'] ?? '') === 'blocked' ? 'blocked' : 'active';
    $name = str_clean($in['name'] ?? '', 60);
    $role = ($in['role'] ?? '') === 'admin' ? 'admin' : 'user';
    $hasRole = array_key_exists('role', $in);
    Store::update('users', function ($users) use ($id, $status, $name, $role, $hasRole, $hasStatus) {
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                if ($hasStatus) {
                    $u['status'] = $status;
                }
                if ($name !== '') {
                    $u['name'] = $name;
                }
                if ($hasRole) {
                    $u['role'] = $role;
                }
            }
        }
        unset($u);
        return $users;
    }, []);
    log_event($a['id'], 'user_save', $id . ' ' . ($hasStatus ? $status : '') . ($hasRole ? ' ' . $role : ''));
    json_out(['ok' => true]);
}

function admin_balance(array $in): void
{
    $a = require_admin();
    $id = str_clean($in['id'] ?? '', 40);
    $amount = money($in['amount'] ?? 0);
    $mode = str_clean($in['mode'] ?? 'add', 10);
    if ($amount <= 0) {
        json_out(['ok' => false, 'error' => 'সঠিক পরিমাণ দিন']);
    }
    $err = null;
    Store::update('users', function ($users) use ($id, $amount, $mode, &$err) {
        foreach ($users as &$u) {
            if ($u['id'] !== $id) {
                continue;
            }
            if ($mode === 'set') {
                $u['balance'] = $amount;
            } elseif ($mode === 'sub') {
                $u['balance'] = max(0, money(($u['balance'] ?? 0) - $amount));
            } else {
                $u['balance'] = money(($u['balance'] ?? 0) + $amount);
            }
            return $users;
        }
        unset($u);
        $err = 'ইউজার নেই';
        return $users;
    }, []);
    if ($err) {
        json_out(['ok' => false, 'error' => $err]);
    }
    $fresh = find_by_id(Store::get('users', []), $id);
    notify($id, 'ব্যালেন্স আপডেট', 'আপনার ব্যালেন্স এখন ৳' . fmt_money($fresh['balance'] ?? 0), 'payment');
    log_event($a['id'], 'balance_adjust', $id, ['amount' => $amount, 'mode' => $mode]);
    json_out(['ok' => true, 'user' => public_user($fresh)]);
}

function admin_payments(): void
{
    require_admin();
    json_out(['ok' => true, 'payments' => array_slice(Store::get('payments', []), 0, 400)]);
}

function admin_payment_action(array $in): void
{
    $a = require_admin();
    $id = str_clean($in['id'] ?? '', 40);
    $do = str_clean($in['do'] ?? '', 20);
    if (!in_array($do, ['approve', 'reject'], true)) {
        json_out(['ok' => false, 'error' => 'অবৈধ']);
    }
    $err = null;
    $pay = null;
    Store::update('payments', function ($rows) use ($id, $do, &$err, &$pay) {
        foreach ($rows as &$p) {
            if (($p['id'] ?? '') !== $id) {
                continue;
            }
            if (($p['status'] ?? '') !== 'pending') {
                $err = 'ইতিমধ্যে প্রসেস হয়েছে';
                return $rows;
            }
            $p['status'] = $do === 'approve' ? 'approved' : 'rejected';
            $p['updated_at'] = now();
            $pay = $p;
            return $rows;
        }
        unset($p);
        $err = 'পেমেন্ট নেই';
        return $rows;
    }, []);
    if ($err) {
        json_out(['ok' => false, 'error' => $err]);
    }
    if ($do === 'approve') {
        credit_user($pay['user_id'], money($pay['amount']), 'ডিপোজিট ' . $pay['trx']);
        notify($pay['user_id'], 'ডিপোজিট সফল', '৳' . fmt_money($pay['amount']) . ' ব্যালেন্সে যোগ হয়েছে। Trx: ' . $pay['trx'], 'payment');
        log_event($a['id'], 'payment_approve', $pay['trx']);
    } else {
        notify($pay['user_id'], 'ডিপোজিট বাতিল', 'Trx ' . $pay['trx'] . ' গ্রহণ করা হয়নি।', 'payment');
        log_event($a['id'], 'payment_reject', $pay['trx']);
    }
    json_out(['ok' => true, 'payment' => $pay]);
}

function admin_methods_save(array $in): void
{
    $a = require_admin();
    $list = $in['methods'] ?? null;
    if (!is_array($list)) {
        $id = str_clean($in['id'] ?? '', 30);
        Store::update('methods', function ($ms) use ($in, $id) {
            foreach ($ms as &$m) {
                if ($m['id'] === $id) {
                    foreach (['name', 'name_bn', 'number', 'type', 'bank_name', 'account_name', 'color', 'instructions', 'status'] as $k) {
                        if (isset($in[$k])) {
                            $m[$k] = str_clean((string)$in[$k], 240);
                        }
                    }
                    if (isset($in['min'])) {
                        $m['min'] = money($in['min']);
                    }
                    if (isset($in['auto'])) {
                        $m['auto'] = (bool)$in['auto'];
                    }
                }
            }
            unset($m);
            return $ms;
        }, []);
        log_event($a['id'], 'method_save', $id);
        json_out(['ok' => true, 'methods' => Store::get('methods', [])]);
    }
    json_out(['ok' => false, 'error' => 'ডেটা সঠিক নয়']);
}

function admin_operators_save(array $in): void
{
    $a = require_admin();
    $id = str_clean($in['id'] ?? '', 20);
    Store::update('operators', function ($ops) use ($in, $id) {
        foreach ($ops as &$o) {
            if ($o['id'] === $id) {
                foreach (['name', 'name_bn', 'short', 'color', 'status'] as $k) {
                    if (isset($in[$k])) {
                        $o[$k] = str_clean((string)$in[$k], 80);
                    }
                }
                if (isset($in['prefixes']) && is_array($in['prefixes'])) {
                    $o['prefixes'] = array_values($in['prefixes']);
                } elseif (isset($in['prefixes']) && is_string($in['prefixes'])) {
                    $o['prefixes'] = array_values(array_filter(array_map('trim', explode(',', $in['prefixes']))));
                }
            }
        }
        unset($o);
        return $ops;
    }, []);
    log_event($a['id'], 'operator_save', $id);
    json_out(['ok' => true, 'operators' => Store::get('operators', [])]);
}

function admin_notify(array $in): void
{
    $a = require_admin();
    $title = str_clean($in['title'] ?? '', 80);
    $body = str_clean($in['body'] ?? '', 400);
    $to = str_clean($in['to'] ?? 'all', 40);
    if ($title === '' || $body === '') {
        json_out(['ok' => false, 'error' => 'টাইটেল ও মেসেজ দিন']);
    }
    notify($to === '' ? 'all' : $to, $title, $body, 'system');
    log_event($a['id'], 'notify', $title);
    json_out(['ok' => true]);
}

function admin_logs(): void
{
    require_admin();
    json_out(['ok' => true, 'logs' => array_slice(Store::get('logs', []), 0, 400)]);
}

function admin_settings_save(array $in): void
{
    $a = require_admin();
    if (empty($in) || (count($in) === 1 && isset($in['_token']))) {
        json_out(['ok' => true, 'settings' => Store::get('settings', [])]);
    }
    $keys = ['site_name', 'tagline', 'support_phone', 'whatsapp', 'notice', 'ticker', 'house_template', 'primary'];
    Store::update('settings', function ($s) use ($in, $keys) {
        if (!is_array($s)) {
            $s = [];
        }
        foreach ($keys as $k) {
            if (isset($in[$k])) {
                $s[$k] = str_clean((string)$in[$k], 500);
            }
        }
        foreach (['min_deposit', 'min_recharge', 'max_recharge', 'min_withdraw', 'max_withdraw', 'min_bill', 'max_bill', 'min_transfer'] as $k) {
            if (isset($in[$k])) {
                $s[$k] = money($in[$k]);
            }
        }
        if (isset($in['maintenance'])) {
            $s['maintenance'] = (bool)$in['maintenance'];
        }
        if (!empty($in['admin_password']) && strlen((string)$in['admin_password']) >= 6) {
            // handled below
        }
        return $s;
    }, []);
    if (!empty($in['admin_password']) && strlen((string)$in['admin_password']) >= 6) {
        Store::update('admins', function ($ads) use ($a, $in) {
            foreach ($ads as &$row) {
                if ($row['id'] === $a['id']) {
                    $row['password'] = password_hash((string)$in['admin_password'], PASSWORD_DEFAULT);
                }
            }
            unset($row);
            return $ads;
        }, []);
    }
    log_event($a['id'], 'settings_save', '');
    json_out(['ok' => true, 'settings' => Store::get('settings', [])]);
}

function admin_bills_save(array $in): void
{
    $a = require_admin();
    $id = str_clean($in['id'] ?? '', 30);
    if ($id === '') {
        json_out(['ok' => true, 'bills' => Store::get('bills', [])]);
    }
    Store::update('bills', function ($rows) use ($in, $id) {
        if (!is_array($rows)) {
            $rows = [];
        }
        $found = false;
        foreach ($rows as &$b) {
            if (($b['id'] ?? '') !== $id) {
                continue;
            }
            $found = true;
            foreach (['name', 'name_bn', 'short', 'type', 'color', 'hint', 'status'] as $k) {
                if (isset($in[$k])) {
                    $b[$k] = str_clean((string)$in[$k], 120);
                }
            }
        }
        unset($b);
        if (!$found) {
            $rows[] = [
                'id' => $id,
                'name' => str_clean((string)($in['name'] ?? $id), 40),
                'name_bn' => str_clean((string)($in['name_bn'] ?? $id), 40),
                'short' => str_clean((string)($in['short'] ?? strtoupper($id)), 12),
                'type' => str_clean((string)($in['type'] ?? 'other'), 20),
                'color' => str_clean((string)($in['color'] ?? '#0f766e'), 20),
                'hint' => str_clean((string)($in['hint'] ?? ''), 80),
                'status' => ($in['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            ];
        }
        return $rows;
    }, []);
    log_event($a['id'], 'bill_save', $id);
    json_out(['ok' => true, 'bills' => Store::get('bills', [])]);
}

function admin_copy(array $in): void
{
    require_admin();
    $id = str_clean($in['id'] ?? '', 40);
    $order = find_by_id(Store::get('orders', []), $id);
    if (!$order) {
        json_out(['ok' => false, 'error' => 'অর্ডার নেই']);
    }
    $ops = Store::get('operators', []);
    $op = find_by_id($ops, $order['operator'] ?? '') ?: ['short' => strtoupper($order['operator'] ?? ''), 'name_bn' => ''];
    $s = Store::get('settings', []);
    $tpl = $s['house_template'] ?? "[{op}] {title}\nMSISDN: {number}\nTK: {price}\nOID: {code}";
    $map = [
        '{op}' => $op['short'] ?? '',
        '{op_bn}' => $op['name_bn'] ?? '',
        '{title}' => $order['title'] ?? '',
        '{volume}' => $order['volume'] ?? '',
        '{validity}' => $order['validity'] ?? '',
        '{number}' => $order['number'] ?? '',
        '{price}' => fmt_money($order['amount'] ?? 0),
        '{code}' => $order['code'] ?? '',
        '{type}' => $order['kind'] ?? '',
        '{user}' => $order['user_phone'] ?? '',
    ];
    $text = strtr($tpl, $map);
    json_out(['ok' => true, 'text' => $text, 'order' => $order]);
}

function gateway_hook(array $in): void
{
    $s = Store::get('settings', []);
    $secret = str_clean($in['secret'] ?? ($_GET['secret'] ?? ''), 80);
    $expect = hash('sha256', APP_KEY . ':hook');
    if ($secret !== $expect && $secret !== APP_KEY) {
        json_out(['ok' => false, 'error' => 'forbidden'], 403);
    }
    $trx = strtoupper(str_clean($in['trx'] ?? '', 40));
    $amount = money($in['amount'] ?? 0);
    $method = str_clean($in['method'] ?? '', 20);
    if ($trx === '' || $amount <= 0) {
        json_out(['ok' => false, 'error' => 'invalid']);
    }
    $matched = null;
    Store::update('payments', function ($rows) use ($trx, $amount, $method, &$matched) {
        foreach ($rows as &$p) {
            if (strtoupper((string)$p['trx']) !== $trx) {
                continue;
            }
            if (($p['status'] ?? '') !== 'pending') {
                $matched = $p;
                return $rows;
            }
            if (abs(money($p['amount']) - $amount) > 0.5) {
                continue;
            }
            if ($method !== '' && ($p['method'] ?? '') !== $method) {
                continue;
            }
            $p['status'] = 'approved';
            $p['updated_at'] = now();
            $p['auto'] = true;
            $matched = $p;
            return $rows;
        }
        unset($p);
        return $rows;
    }, []);
    if (!$matched) {
        json_out(['ok' => false, 'error' => 'no match']);
    }
    if (!empty($matched['auto']) && ($matched['status'] ?? '') === 'approved') {
        $already = false;
        $logs = Store::get('logs', []);
        foreach ($logs as $l) {
            if (($l['action'] ?? '') === 'credit' && ($l['meta']['trx'] ?? '') === $trx) {
                $already = true;
                break;
            }
        }
        if (!$already) {
            credit_user($matched['user_id'], money($matched['amount']), 'হুক ডিপোজিট ' . $trx);
            notify($matched['user_id'], 'ডিপোজিট সফল', '৳' . fmt_money($matched['amount']) . ' অটো গেটওয়েতে যোগ হয়েছে।', 'payment');
        }
    }
    json_out(['ok' => true, 'payment' => $matched]);
}
