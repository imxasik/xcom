<?php
declare(strict_types=1);

function pending_on_number(string $number): bool
{
    $orders = Store::get('orders', []);
    foreach ($orders as $o) {
        if (($o['number'] ?? '') === $number && ($o['status'] ?? '') === 'pending') {
            return true;
        }
    }
    return false;
}

function take_idempotency(string $key): bool
{
    $fresh = true;
    Store::update('idempotency', function ($box) use ($key, &$fresh) {
        if (!is_array($box)) {
            $box = [];
        }
        $now = time();
        foreach ($box as $k => $t) {
            if ($now - (int)$t > 120) {
                unset($box[$k]);
            }
        }
        if (isset($box[$key])) {
            $fresh = false;
            return $box;
        }
        $box[$key] = $now;
        return $box;
    }, []);
    return $fresh;
}

function place_order(array $user, array $offer, string $number, string $kind, float $amount, float $commission, bool $lockNumber = true): array
{
    $err = null;
    $created = null;
    Store::update('users', function ($users) use ($user, $amount, &$err) {
        if (!is_array($users)) {
            $users = [];
        }
        foreach ($users as &$u) {
            if ($u['id'] !== $user['id']) {
                continue;
            }
            $avail = money(($u['balance'] ?? 0) - ($u['pending'] ?? 0));
            if ($avail + 0.001 < $amount) {
                $err = 'পর্যাপ্ত ব্যালেন্স নেই';
                return $users;
            }
            $u['pending'] = money(($u['pending'] ?? 0) + $amount);
            return $users;
        }
        unset($u);
        $err = 'ইউজার পাওয়া যায়নি';
        return $users;
    }, []);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }

    $order = [
        'id' => uid('OR'),
        'code' => order_code(),
        'user_id' => $user['id'],
        'user_phone' => $user['phone'],
        'user_name' => $user['name'],
        'offer_id' => $offer['id'] ?? '',
        'operator' => $offer['operator'] ?? '',
        'title' => $offer['title'] ?? offer_line($offer, null, true) ?: kind_bn($kind),
        'volume' => $offer['volume'] ?? '',
        'validity' => $offer['validity'] ?? '',
        'type' => $offer['type'] ?? $kind,
        'kind' => $kind,
        'number' => $number,
        'amount' => $amount,
        'commission' => $commission,
        'status' => 'pending',
        'note' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $dup = false;
    Store::update('orders', function ($orders) use ($order, &$dup, &$created) {
        if (!is_array($orders)) {
            $orders = [];
        }
        foreach ($orders as $o) {
            if (($o['number'] ?? '') === $order['number'] && ($o['status'] ?? '') === 'pending') {
                $dup = true;
                return $orders;
            }
        }
        array_unshift($orders, $order);
        $created = $order;
        return array_slice($orders, 0, 8000);
    }, []);

    if ($dup) {
        Store::update('users', function ($users) use ($user, $amount) {
            if (!is_array($users)) {
                return [];
            }
            foreach ($users as &$u) {
                if ($u['id'] === $user['id']) {
                    $u['pending'] = max(0, money(($u['pending'] ?? 0) - $amount));
                }
            }
            unset($u);
            return $users;
        }, []);
        return ['ok' => false, 'error' => 'এই নম্বরে ইতিমধ্যে একটি পেন্ডিং অর্ডার আছে'];
    }

    log_event($user['id'], 'order_hit', $order['code'] . ' ' . $number, ['amount' => $amount]);
    push_send_to(
        'admin',
        'all',
        '🔔 নতুন ' . kind_bn($kind) . ' হিট',
        ($user['name'] ?? '') . ' (' . ($user['phone'] ?? '') . ') · ' . order_line($created, true) . ' · ' . $number . ' · ৳' . fmt_money($amount),
        'admin_order',
        './nx.php'
    );
    return ['ok' => true, 'order' => $created];
}

function user_bill(array $in): void
{
    $u = require_user();
    if (!rate_limit('ord:' . $u['id'], 30, 60)) {
        json_out(['ok' => false, 'error' => 'অনেক দ্রুত চাপ দিচ্ছেন। একটু অপেক্ষা করুন।'], 429);
    }
    $pid = str_clean($in['provider'] ?? '', 30);
    $account = str_clean($in['account'] ?? '', 40);
    $account = preg_replace('/\s+/', '', $account) ?? $account;
    $amount = money($in['amount'] ?? 0);
    $idem = str_clean($in['idem'] ?? '', 80);
    $s = Store::get('settings', []);
    $min = money($s['min_bill'] ?? 50);
    $max = money($s['max_bill'] ?? 20000);
    $prov = find_by_id(Store::get('bills', []), $pid);
    if (!$prov || ($prov['status'] ?? '') !== 'active') {
        json_out(['ok' => false, 'error' => 'কোম্পানি বাছাই করুন']);
    }
    if ($account === '' || mb_len($account) < 4) {
        json_out(['ok' => false, 'error' => 'সঠিক কাস্টমার / অ্যাকাউন্ট নম্বর দিন']);
    }
    if ($amount < $min || $amount > $max) {
        json_out(['ok' => false, 'error' => 'বিল ' . fmt_money($min) . ' থেকে ' . fmt_money($max) . ' টাকার মধ্যে দিন']);
    }
    if ($idem === '' || !take_idempotency($u['id'] . ':b:' . $idem)) {
        json_out(['ok' => false, 'error' => 'অনুরোধ ইতিমধ্যে প্রসেস হয়েছে। নতুন করে চাপবেন না।']);
    }
    if (pending_on_number($account)) {
        json_out(['ok' => false, 'error' => 'এই অ্যাকাউন্টে একটি অর্ডার পেন্ডিং আছে']);
    }
    $offer = [
        'id' => $prov['id'],
        'operator' => $prov['id'],
        'title' => 'বিল · ' . ($prov['name_bn'] ?? $prov['name']),
        'volume' => $account,
        'validity' => $prov['name'] ?? '',
        'type' => 'bill',
    ];
    $res = place_order($u, $offer, $account, 'bill', $amount, 0, true);
    if (!$res['ok']) {
        json_out(['ok' => false, 'error' => $res['error']]);
    }
    notify($u['id'], 'বিল পেন্ডিং', ($prov['name_bn'] ?? $prov['name']) . ' · ' . $account . ' · ৳' . fmt_money($amount) . ' যাচাইয়ের অপেক্ষায়।', 'order');
    $fresh = find_by_id(Store::get('users', []), $u['id']);
    json_out(['ok' => true, 'order' => $res['order'], 'user' => public_user($fresh)]);
}

function user_withdraw(array $in): void
{
    $u = require_user();
    if (!rate_limit('ord:' . $u['id'], 20, 60)) {
        json_out(['ok' => false, 'error' => 'অনেক দ্রুত চাপ দিচ্ছেন। একটু অপেক্ষা করুন।'], 429);
    }
    $mid = str_clean($in['method'] ?? '', 30);
    $amount = money($in['amount'] ?? 0);
    $dest = str_clean($in['number'] ?? '', 40);
    $idem = str_clean($in['idem'] ?? '', 80);
    $s = Store::get('settings', []);
    $min = money($s['min_withdraw'] ?? 100);
    $max = money($s['max_withdraw'] ?? 50000);
    $m = find_by_id(Store::get('methods', []), $mid);
    if (!$m || ($m['status'] ?? '') !== 'active') {
        json_out(['ok' => false, 'error' => 'উইথড্র মাধ্যম বাছাই করুন']);
    }
    $isBank = ($m['id'] ?? '') === 'bank' || ($m['type'] ?? '') === 'Current';
    $number = $isBank ? preg_replace('/\s+/', '', $dest) : bd_number($dest);
    if (!$number || ($isBank && strlen((string)$number) < 8)) {
        json_out(['ok' => false, 'error' => $isBank ? 'সঠিক অ্যাকাউন্ট নম্বর দিন' : 'সঠিক মোবাইল নম্বর দিন']);
    }
    if ($amount < $min || $amount > $max) {
        json_out(['ok' => false, 'error' => 'উইথড্র ' . fmt_money($min) . ' থেকে ' . fmt_money($max) . ' টাকার মধ্যে দিন']);
    }
    if ($idem === '' || !take_idempotency($u['id'] . ':w:' . $idem)) {
        json_out(['ok' => false, 'error' => 'অনুরোধ ইতিমধ্যে প্রসেস হয়েছে। নতুন করে চাপবেন না।']);
    }
    if (pending_user_kind($u['id'], 'withdraw')) {
        json_out(['ok' => false, 'error' => 'একটি উইথড্র ইতিমধ্যে পেন্ডিং আছে']);
    }
    $offer = [
        'id' => $m['id'],
        'operator' => $m['id'],
        'title' => 'উইথড্র · ' . ($m['name_bn'] ?? $m['name']),
        'volume' => (string)$number,
        'validity' => $m['type'] ?? '',
        'type' => 'withdraw',
    ];
    $res = place_order($u, $offer, (string)$number, 'withdraw', $amount, 0, false);
    if (!$res['ok']) {
        json_out(['ok' => false, 'error' => $res['error']]);
    }
    notify($u['id'], order_line($res['order'] ?? $offer, true), 'উইথড্র পেন্ডিং · ৳' . fmt_money($amount), 'order');
    $fresh = find_by_id(Store::get('users', []), $u['id']);
    json_out(['ok' => true, 'order' => $res['order'], 'user' => public_user($fresh)]);
}

function user_transfer(array $in): void
{
    $u = require_user();
    if (!rate_limit('ord:' . $u['id'], 20, 60)) {
        json_out(['ok' => false, 'error' => 'অনেক দ্রুত চাপ দিচ্ছেন। একটু অপেক্ষা করুন।'], 429);
    }
    $phone = bd_number((string)($in['phone'] ?? ''));
    $amount = money($in['amount'] ?? 0);
    $idem = str_clean($in['idem'] ?? '', 80);
    $s = Store::get('settings', []);
    $min = money($s['min_transfer'] ?? 10);
    if (!$phone) {
        json_out(['ok' => false, 'error' => 'প্রাপকের সঠিক মোবাইল নম্বর দিন']);
    }
    if ($phone === ($u['phone'] ?? '')) {
        json_out(['ok' => false, 'error' => 'নিজের নম্বরে ট্রান্সফার করা যাবে না']);
    }
    if ($amount < $min) {
        json_out(['ok' => false, 'error' => 'সর্বনিম্ন ট্রান্সফার ৳' . fmt_money($min)]);
    }
    if ($idem === '' || !take_idempotency($u['id'] . ':t:' . $idem)) {
        json_out(['ok' => false, 'error' => 'অনুরোধ ইতিমধ্যে প্রসেস হয়েছে। নতুন করে চাপবেন না।']);
    }
    $err = null;
    $to = null;
    Store::update('users', function ($users) use ($u, $phone, $amount, &$err, &$to) {
        if (!is_array($users)) {
            $users = [];
        }
        $fromI = null;
        $toI = null;
        foreach ($users as $i => $row) {
            if (($row['id'] ?? '') === $u['id']) {
                $fromI = $i;
            }
            if (($row['phone'] ?? '') === $phone) {
                $toI = $i;
            }
        }
        if ($fromI === null) {
            $err = 'ইউজার পাওয়া যায়নি';
            return $users;
        }
        if ($toI === null) {
            $err = 'এই নম্বরে কোনো একাউন্ট নেই';
            return $users;
        }
        if (($users[$toI]['status'] ?? '') === 'blocked') {
            $err = 'প্রাপকের একাউন্ট ব্লক করা';
            return $users;
        }
        $avail = money(($users[$fromI]['balance'] ?? 0) - ($users[$fromI]['pending'] ?? 0));
        if ($avail + 0.001 < $amount) {
            $err = 'পর্যাপ্ত ব্যালেন্স নেই';
            return $users;
        }
        $users[$fromI]['balance'] = money(($users[$fromI]['balance'] ?? 0) - $amount);
        $users[$toI]['balance'] = money(($users[$toI]['balance'] ?? 0) + $amount);
        $to = $users[$toI];
        return $users;
    }, []);
    if ($err) {
        json_out(['ok' => false, 'error' => $err]);
    }
    $order = [
        'id' => uid('OR'),
        'code' => order_code(),
        'user_id' => $u['id'],
        'user_phone' => $u['phone'],
        'user_name' => $u['name'],
        'offer_id' => '',
        'operator' => '',
        'title' => 'ট্রান্সফার · ' . $phone,
        'volume' => '৳' . fmt_money($amount),
        'validity' => 'তাৎক্ষণিক',
        'type' => 'transfer',
        'kind' => 'transfer',
        'number' => $phone,
        'amount' => $amount,
        'commission' => 0,
        'status' => 'confirmed',
        'note' => $to['name'] ?? '',
        'created_at' => now(),
        'updated_at' => now(),
    ];
    Store::update('orders', function ($orders) use ($order) {
        if (!is_array($orders)) {
            $orders = [];
        }
        array_unshift($orders, $order);
        return array_slice($orders, 0, 8000);
    }, []);
    notify($u['id'], order_line($order, true), 'ট্রান্সফার সফল · ৳' . fmt_money($amount) . ' → ' . $phone, 'order');
    notify($to['id'], 'ব্যালেন্স পেয়েছেন', $u['phone'] . ' থেকে ৳' . fmt_money($amount) . ' ট্রান্সফার হয়েছে।', 'payment');
    log_event($u['id'], 'transfer', $phone, ['amount' => $amount, 'to' => $to['id']]);
    $fresh = find_by_id(Store::get('users', []), $u['id']);
    json_out(['ok' => true, 'order' => $order, 'user' => public_user($fresh)]);
}
