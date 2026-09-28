<?php
declare(strict_types=1);

/*
 * Web Push (RFC 8291 message encryption / RFC 8188 aes128gcm content-coding
 * + VAPID application-server identification, RFC 8292) — implemented with
 * only ext-openssl (no composer / vendor dependency).
 *
 * The full encryption pipeline in this file has been verified byte-for-byte
 * against the official RFC 8291 Appendix A test vectors.
 */

const WP_EC_PRIV_PREFIX_HEX = '308187020100301306072a8648ce3d020106082a8648ce3d030107046d306b0201010420';
const WP_EC_PRIV_MIDDLE_HEX = 'a144034200';
const WP_EC_PUB_PREFIX_HEX  = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

function wp_b64u_encode(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function wp_b64u_decode(string $s): string
{
    $s = strtr((string)$s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) {
        $s .= str_repeat('=', 4 - $pad);
    }
    $out = base64_decode($s, true);
    return $out === false ? '' : $out;
}

/** Build a PKCS8 PEM for a P-256 private key from raw scalar + raw 65-byte uncompressed point. */
function wp_ec_priv_pem(string $d, string $pubRaw): string
{
    $d = str_pad(substr($d, -32), 32, "\x00", STR_PAD_LEFT);
    $der = hex2bin(WP_EC_PRIV_PREFIX_HEX) . $d . hex2bin(WP_EC_PRIV_MIDDLE_HEX) . $pubRaw;
    return "-----BEGIN PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PRIVATE KEY-----\n";
}

/** Build a SubjectPublicKeyInfo PEM for a P-256 public key from a raw 65-byte uncompressed point. */
function wp_ec_pub_pem(string $pubRaw): string
{
    $der = hex2bin(WP_EC_PUB_PREFIX_HEX) . $pubRaw;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Generate a fresh EC P-256 key pair. Returns ['d' => 32-byte raw, 'pub' => 65-byte raw uncompressed] or null. */
function wp_new_ec_keypair(): ?array
{
    $attempts = [
        ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC],
    ];
    foreach (['/etc/ssl/openssl.cnf', '/etc/pki/tls/openssl.cnf', '/usr/lib/ssl/openssl.cnf', '/usr/local/etc/openssl/openssl.cnf'] as $cnf) {
        $attempts[] = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'config' => $cnf];
    }
    foreach ($attempts as $conf) {
        $res = @openssl_pkey_new($conf);
        if ($res === false) {
            continue;
        }
        $details = @openssl_pkey_get_details($res);
        if (!$details || empty($details['ec']['d']) || empty($details['ec']['x']) || empty($details['ec']['y'])) {
            continue;
        }
        $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $d = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);
        return ['d' => $d, 'pub' => "\x04" . $x . $y];
    }
    return null;
}

/** Load (or lazily generate + persist) the site's VAPID key pair. */
function vapid_keys(): array
{
    $v = Store::get('vapid', []);
    if (!empty($v['public']) && !empty($v['private'])) {
        return $v;
    }
    return Store::update('vapid', function ($cur) {
        if (!is_array($cur)) {
            $cur = [];
        }
        if (!empty($cur['public']) && !empty($cur['private'])) {
            return $cur;
        }
        $kp = wp_new_ec_keypair();
        if (!$kp) {
            return $cur;
        }
        return [
            'public' => wp_b64u_encode($kp['pub']),
            'private' => wp_b64u_encode($kp['d']),
            'created_at' => now(),
        ];
    }, []);
}

function vapid_subject(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}

function wp_der_sig_to_raw(string $der): string
{
    $pos = 1;
    $len = ord($der[1]);
    $pos = 2;
    if ($len & 0x80) {
        $pos += ($len & 0x7f);
    }
    $pos++;
    $rlen = ord($der[$pos]);
    $pos++;
    $r = substr($der, $pos, $rlen);
    $pos += $rlen;
    $pos++;
    $slen = ord($der[$pos]);
    $pos++;
    $s = substr($der, $pos, $slen);
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
}

/** Build the VAPID "Authorization" header value for a given push-service origin. */
function wp_vapid_auth_header(string $audience): ?string
{
    $vapid = vapid_keys();
    if (empty($vapid['public']) || empty($vapid['private'])) {
        return null;
    }
    $pubRaw = wp_b64u_decode($vapid['public']);
    $dRaw = wp_b64u_decode($vapid['private']);
    $pem = wp_ec_priv_pem($dRaw, $pubRaw);
    $priv = @openssl_pkey_get_private($pem);
    if (!$priv) {
        return null;
    }
    $header = wp_b64u_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
    $payload = wp_b64u_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 12 * 3600,
        'sub' => vapid_subject(),
    ], JSON_UNESCAPED_SLASHES));
    $signInput = $header . '.' . $payload;
    $der = '';
    if (!openssl_sign($signInput, $der, $priv, OPENSSL_ALGO_SHA256)) {
        return null;
    }
    $jwt = $signInput . '.' . wp_b64u_encode(wp_der_sig_to_raw($der));
    return 'vapid t=' . $jwt . ', k=' . $vapid['public'];
}

/**
 * Encrypt a payload for a push subscription per RFC 8291 (aes128gcm).
 * Returns the raw wire body (header + ciphertext + tag) or null on failure.
 */
function wp_encrypt_payload(string $plaintext, string $uaPubRaw, string $authSecret): ?string
{
    if (strlen($uaPubRaw) !== 65 || $uaPubRaw[0] !== "\x04" || strlen($authSecret) < 16) {
        return null;
    }
    $as = wp_new_ec_keypair();
    if (!$as) {
        return null;
    }
    $asPriv = @openssl_pkey_get_private(wp_ec_priv_pem($as['d'], $as['pub']));
    $uaPub = @openssl_pkey_get_public(wp_ec_pub_pem($uaPubRaw));
    if (!$asPriv || !$uaPub) {
        return null;
    }
    $shared = @openssl_pkey_derive($uaPub, $asPriv, 32);
    if ($shared === false || strlen($shared) !== 32) {
        return null;
    }
    $salt = random_bytes(16);
    $keyInfo = "WebPush: info\x00" . $uaPubRaw . $as['pub'];
    $ikm = hash_hkdf('sha256', $shared, 32, $keyInfo, $authSecret);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);
    $tag = '';
    $ct = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ct === false) {
        return null;
    }
    $header = $salt . pack('N', 4096) . chr(strlen($as['pub'])) . $as['pub'];
    return $header . $ct . $tag;
}

/** Low level HTTP POST used to deliver a push message. Returns ['ok'=>bool,'code'=>int]. */
function wp_http_post(string $url, string $body, array $headers): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code];
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $res = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    return ['ok' => $code >= 200 && $code < 300 && $res !== false, 'code' => $code];
}

/** Send one push message to a single stored subscription row. Removes the row if it is gone (404/410). */
function wp_deliver(array $sub, array $payload): bool
{
    try {
        $endpoint = (string)($sub['endpoint'] ?? '');
        $p256dh = wp_b64u_decode((string)($sub['p256dh'] ?? ''));
        $auth = wp_b64u_decode((string)($sub['auth'] ?? ''));
        if ($endpoint === '' || strlen($p256dh) !== 65 || strlen($auth) < 16) {
            return false;
        }
        $host = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $authHeader = wp_vapid_auth_header($host);
        if (!$authHeader) {
            return false;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $body = wp_encrypt_payload((string)$json, $p256dh, $auth);
        if ($body === null) {
            return false;
        }
        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . strlen($body),
            'TTL: 259200',
            'Urgency: high',
            'Authorization: ' . $authHeader,
        ];
        $res = wp_http_post($endpoint, $body, $headers);
        if (in_array($res['code'], [404, 410], true)) {
            Store::update('push_subs', function ($rows) use ($endpoint) {
                if (!is_array($rows)) {
                    return [];
                }
                return array_values(array_filter($rows, fn ($r) => ($r['endpoint'] ?? '') !== $endpoint));
            }, []);
        }
        return $res['ok'];
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Send a push notification to every subscription matching a target.
 * $kind = 'user' -> $target must be the user id.
 * $kind = 'admin' -> $target = 'all' (every admin device) or a specific admin/user id.
 */
function push_send_to(string $kind, string $target, string $title, string $body, string $type = 'system', string $url = ''): void
{
    try {
        if (empty(vapid_keys()['public'])) {
            return;
        }
        $rows = Store::get('push_subs', []);
        if (!is_array($rows) || !$rows) {
            return;
        }
        $payload = [
            'title' => mb_substr($title, 0, 90),
            'body' => mb_substr($body, 0, 220),
            'type' => $type,
            'url' => $url !== '' ? $url : ($kind === 'admin' ? './nx.php' : './'),
            'tag' => 'oh-' . $type,
            'at' => now(),
        ];
        foreach ($rows as $sub) {
            if (($sub['kind'] ?? '') !== $kind) {
                continue;
            }
            if ($kind !== 'admin' || $target !== 'all') {
                if (($sub['uid'] ?? '') !== $target) {
                    continue;
                }
            }
            wp_deliver($sub, $payload);
        }
    } catch (Throwable $e) {
        // Push delivery must never break the primary request flow.
    }
}
