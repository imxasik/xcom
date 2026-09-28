<?php
declare(strict_types=1);

function sessions_get(): array
{
    return Store::get('sessions', []);
}

function create_session(string $kind, string $uid): string
{
    $token = bin2hex(random_bytes(32));
    $exp = time() + (SESSION_DAYS * 86400);
    Store::update('sessions', function ($ss) use ($token, $kind, $uid, $exp) {
        if (!is_array($ss)) {
            $ss = [];
        }
        $now = time();
        foreach ($ss as $k => $s) {
            if (($s['exp'] ?? 0) < $now) {
                unset($ss[$k]);
            }
        }
        $ss[$token] = [
            'kind' => $kind,
            'uid' => $uid,
            'exp' => $exp,
            'ip' => client_ip(),
            'at' => now(),
        ];
        return $ss;
    }, []);
    return $token;
}

function session_of(?string $token): ?array
{
    if (!$token) {
        return null;
    }
    $ss = sessions_get();
    $s = $ss[$token] ?? null;
    if (!$s || ($s['exp'] ?? 0) < time()) {
        return null;
    }
    return $s;
}

function destroy_session(?string $token): void
{
    if (!$token) {
        return;
    }
    Store::update('sessions', function ($ss) use ($token) {
        if (!is_array($ss)) {
            return [];
        }
        unset($ss[$token]);
        return $ss;
    }, []);
}

function current_user(): ?array
{
    $s = session_of(bearer_token());
    if (!$s) {
        return null;
    }
    $kind = $s['kind'] ?? '';
    if ($kind !== 'user' && $kind !== 'admin') {
        return null;
    }
    $users = Store::get('users', []);
    $u = find_by_id($users, (string)$s['uid']);
    if (!$u || ($u['status'] ?? '') === 'blocked') {
        return null;
    }
    return $u;
}

function require_user(): array
{
    $u = current_user();
    if (!$u) {
        json_out(['ok' => false, 'error' => 'লগইন প্রয়োজন'], 401);
    }
    return $u;
}

function require_admin(): array
{
    $s = session_of(bearer_token());
    if (!$s || ($s['kind'] ?? '') !== 'admin') {
        json_out(['ok' => false, 'error' => 'অ্যাকসেস নিষিদ্ধ'], 401);
    }
    $uid = (string)$s['uid'];
    $admins = Store::get('admins', []);
    $a = find_by_id($admins, $uid);
    if ($a && ($a['status'] ?? '') === 'active') {
        return $a;
    }
    $u = find_by_id(Store::get('users', []), $uid);
    if ($u && ($u['role'] ?? '') === 'admin' && ($u['status'] ?? '') !== 'blocked') {
        return [
            'id' => $u['id'],
            'name' => $u['name'] ?? 'Admin',
            'username' => $u['phone'] ?? $u['id'],
            'status' => 'active',
            'from_user' => true,
        ];
    }
    json_out(['ok' => false, 'error' => 'অ্যাকসেস নিষিদ্ধ'], 401);
}

function login_guard(string $key): ?string
{
    $box = Store::get('locks', []);
    $row = $box[$key] ?? null;
    if ($row && ($row['until'] ?? 0) > time()) {
        $left = (int)ceil((($row['until'] - time()) / 60));
        return "অনেকবার ভুল হয়েছে। {$left} মিনিট পরে চেষ্টা করুন।";
    }
    return null;
}

function login_fail(string $key): void
{
    Store::update('locks', function ($box) use ($key) {
        if (!is_array($box)) {
            $box = [];
        }
        $row = $box[$key] ?? ['n' => 0, 'until' => 0];
        if (($row['until'] ?? 0) < time() && ($row['n'] ?? 0) >= LOGIN_MAX_TRIES) {
            $row = ['n' => 0, 'until' => 0];
        }
        $row['n'] = (int)$row['n'] + 1;
        if ($row['n'] >= LOGIN_MAX_TRIES) {
            $row['until'] = time() + (LOGIN_LOCK_MINUTES * 60);
        }
        $box[$key] = $row;
        return $box;
    }, []);
}

function login_ok(string $key): void
{
    Store::update('locks', function ($box) use ($key) {
        if (!is_array($box)) {
            return [];
        }
        unset($box[$key]);
        return $box;
    }, []);
}
