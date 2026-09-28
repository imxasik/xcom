<?php
declare(strict_types=1);

function seed_if_needed(): void
{
    $flag = DATA_DIR . '/.installed';
    if (is_file($flag)) {
        return;
    }

    $operators = [
        [
            'id' => 'gp',
            'name' => 'Grameenphone',
            'name_bn' => 'গ্রামীণফোন',
            'short' => 'GP',
            'prefixes' => ['013', '017'],
            'color' => '#00A651',
            'status' => 'active',
        ],
        [
            'id' => 'robi',
            'name' => 'Robi',
            'name_bn' => 'রবি',
            'short' => 'Robi',
            'prefixes' => ['018'],
            'color' => '#E31E24',
            'status' => 'active',
        ],
        [
            'id' => 'bl',
            'name' => 'Banglalink',
            'name_bn' => 'বাংলালিংক',
            'short' => 'BL',
            'prefixes' => ['014', '019'],
            'color' => '#ED6B23',
            'status' => 'active',
        ],
        [
            'id' => 'tt',
            'name' => 'Teletalk',
            'name_bn' => 'টেলিটক',
            'short' => 'TT',
            'prefixes' => ['015'],
            'color' => '#00A0E6',
            'status' => 'active',
        ],
        [
            'id' => 'airtel',
            'name' => 'Airtel',
            'name_bn' => 'এয়ারটেল',
            'short' => 'AT',
            'prefixes' => ['016'],
            'color' => '#E40000',
            'status' => 'active',
        ],
    ];

    $offers = [];

    $methods = [
        [
            'id' => 'bkash',
            'name' => 'bKash',
            'name_bn' => 'বিকাশ',
            'number' => '01700000001',
            'type' => 'Personal',
            'color' => '#E2136E',
            'instructions' => 'Send Money করুন। রেফারেন্সে আপনার মোবাইল নম্বর দিন। TrxID সেভ রাখুন।',
            'auto' => false,
            'status' => 'active',
            'min' => 50,
        ],
        [
            'id' => 'nagad',
            'name' => 'Nagad',
            'name_bn' => 'নগদ',
            'number' => '01700000002',
            'type' => 'Personal',
            'color' => '#F15A22',
            'instructions' => 'Send Money করুন। TrxID সহ জমা দিন।',
            'auto' => false,
            'status' => 'active',
            'min' => 50,
        ],
        [
            'id' => 'rocket',
            'name' => 'Rocket',
            'name_bn' => 'রকেট',
            'number' => '01700000003-5',
            'type' => 'Personal',
            'color' => '#8C3494',
            'instructions' => 'Send Money (App/USSD *322#)। TrxID দিন।',
            'auto' => false,
            'status' => 'active',
            'min' => 50,
        ],
        [
            'id' => 'upay',
            'name' => 'Upay',
            'name_bn' => 'উপায়',
            'number' => '01700000004',
            'type' => 'Personal',
            'color' => '#F5A623',
            'instructions' => 'Send Money করে TrxID জমা দিন।',
            'auto' => false,
            'status' => 'active',
            'min' => 50,
        ],
        [
            'id' => 'ucash',
            'name' => 'UCash',
            'name_bn' => 'ইউক্যাশ',
            'number' => '01700000005',
            'type' => 'Personal',
            'color' => '#00AEEF',
            'instructions' => 'Send Money করে TrxID জমা দিন।',
            'auto' => false,
            'status' => 'active',
            'min' => 50,
        ],
        [
            'id' => 'bank',
            'name' => 'Bank',
            'name_bn' => 'ব্যাংক',
            'number' => '1234567890123',
            'type' => 'Current',
            'bank_name' => 'Dutch-Bangla Bank',
            'account_name' => 'OfferHub',
            'color' => '#1E3A8A',
            'instructions' => 'নিজ নামে ট্রান্সফার করুন। স্লিপ/রেফারেন্স Trx হিসেবে দিন।',
            'auto' => false,
            'status' => 'active',
            'min' => 100,
        ],
    ];

    $adminPass = password_hash('Imask@123', PASSWORD_DEFAULT);
    $userPass = password_hash('123456', PASSWORD_DEFAULT);

    $admins = [
        [
            'id' => 'ADM001',
            'username' => '01612935059',
            'phone' => '01612935059',
            'name' => 'সুপার অ্যাডমিন',
            'password' => $adminPass,
            'status' => 'active',
            'created_at' => now(),
        ],
    ];

    $users = [];

    $settings = [
        'site_name' => 'OfferHub',
        'tagline' => 'স্মার্ট অফার ও রিচার্জ',
        'support_phone' => '01700000000',
        'whatsapp' => '01700000000',
        'min_deposit' => 50,
        'min_recharge' => 10,
        'max_recharge' => 5000,
        'notice' => 'স্বাগতম! অফার হিট করার আগে নম্বরটি ভালো করে যাচাই করুন। পেন্ডিং অর্ডার কনফার্ম হলেই টাকা কেটে যাবে।',
        'maintenance' => false,
        'primary' => '#0F766E',
        'house_template' => "[{op}] {title}\nMSISDN: {number}\nTK: {price}\nOID: {code}",
    ];

    Store::put('operators', $operators);
    Store::put('offers', $offers);
    Store::put('methods', $methods);
    Store::put('admins', $admins);
    Store::put('users', $users);
    Store::put('settings', $settings);
    Store::put('orders', []);
    Store::put('payments', []);
    Store::put('notifications', [
        [
            'id' => uid('N'),
            'to' => 'all',
            'title' => 'স্বাগতম OfferHub-এ',
            'body' => 'অফার হিট, রিচার্জ ও ব্যালেন্স — সবকিছু এক জায়গায়। পেন্ডিং অর্ডার কনফার্ম হলেই টাকা কাটে।',
            'type' => 'system',
            'reads' => [],
            'at' => now(),
        ],
    ]);
    Store::put('logs', []);
    Store::put('sessions', []);
    Store::put('locks', []);
    Store::put('ratelimit', []);
    Store::put('idempotency', []);
    Store::put('bills', default_bills());

    log_event('system', 'install', 'প্রাথমিক সেটআপ সম্পন্ন');
    file_put_contents($flag, now());
}

function default_bills(): array
{
    $mk = function (string $id, string $name, string $bn, string $type, string $color, string $hint = ''): array {
        return [
            'id' => $id,
            'name' => $name,
            'name_bn' => $bn,
            'short' => strtoupper($id),
            'type' => $type,
            'color' => $color,
            'hint' => $hint,
            'status' => 'active',
        ];
    };
    return [
        $mk('desco', 'DESCO', 'ডেসকো', 'electricity', '#0f766e', 'কাস্টমার নম্বর দিন'),
        $mk('dpdc', 'DPDC', 'ডিপিডিসি', 'electricity', '#1d4ed8', 'অ্যাকাউন্ট নম্বর দিন'),
        $mk('nesco', 'NESCO', 'নেসকো', 'electricity', '#047857', 'কাস্টমার নম্বর দিন'),
        $mk('bpdb', 'BPDB', 'বিপিডিবি', 'electricity', '#b45309', 'কাস্টমার নম্বর দিন'),
        $mk('breb', 'BREB', 'পল্লী বিদ্যুৎ', 'electricity', '#15803d', 'কাস্টমার নম্বর দিন'),
        $mk('titas', 'Titas Gas', 'তিতাস গ্যাস', 'gas', '#c2410c', 'কাস্টমার কোড দিন'),
        $mk('wasa', 'WASA', 'ওয়াসা', 'water', '#0369a1', 'কাস্টমার আইডি দিন'),
        $mk('inet', 'Internet', 'ইন্টারনেট বিল', 'internet', '#7c3aed', 'ইউজার আইডি / অ্যাকাউন্ট'),
    ];
}

function ensure_extra_data(): void
{
    if (!is_file(Store::path('bills'))) {
        Store::put('bills', default_bills());
    }
    $s = Store::get('settings', []);
    if (!is_array($s)) {
        $s = [];
    }
    $defs = [
        'min_withdraw' => 100,
        'max_withdraw' => 50000,
        'min_bill' => 50,
        'max_bill' => 20000,
        'min_transfer' => 10,
    ];
    $changed = false;
    foreach ($defs as $k => $v) {
        if (!isset($s[$k])) {
            $s[$k] = $v;
            $changed = true;
        }
    }
    if ($changed) {
        Store::put('settings', $s);
    }
}
