<?php
// Live HTTP E2E smoke against the running container (127.0.0.1:80).
// Real session + CSRF dance (raw cookie values, X-XSRF-TOKEN = rawurldecode of
// the encrypted cookie, as axios does in a browser). Verifies over REAL HTTP:
//   1) auth + every key page serves its real Inertia component with seeded data
//   2) detail pages for ticket/customer/device/product/invoice
//   3) unauthenticated access is gated to /login
//   4) cross-tenant access to a foreign ticket is denied
// Prints PASS/FAIL per check; exits non-zero on any FAIL.

$base = 'http://127.0.0.1:80';
$pass = 0;
$fail = 0;

function cookieHeader($jar)
{
    $parts = [];
    foreach ($jar as $k => $v) {
        $parts[] = $k . '=' . $v;
    }
    return implode('; ', $parts);
}

function grab($method, $url, $jar, $fields = null, $xsrf = false)
{
    global $base;
    $ch = curl_init();
    $h = ['Accept: text/html'];
    if ($fields !== null) {
        $h[] = 'Content-Type: application/x-www-form-urlencoded';
        if ($xsrf && isset($jar['XSRF-TOKEN'])) {
            $h[] = 'X-XSRF-TOKEN: ' . rawurldecode($jar['XSRF-TOKEN']);
        }
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_URL => $base . $url,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => 0,
    ]);
    if ($jar) {
        curl_setopt($ch, CURLOPT_COOKIE, cookieHeader($jar));
    }
    if ($fields !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    $raw = curl_exec($ch);
    $st = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $loc = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    foreach (array_map('trim', explode("\r\n", substr($raw, 0, $hs))) as $l) {
        if (stripos($l, 'Set-Cookie:') === 0) {
            $p2 = explode(';', trim(substr($l, 11)))[0];
            $kv = explode('=', $p2, 2);
            if (count($kv) === 2) {
                $jar[trim($kv[0])] = $kv[1];
            }
        }
    }
    return [$st, substr($raw, $hs), $loc, $jar];
}

function component($body)
{
    if (preg_match('/data-page="([^"]+)"/', $body, $m)) {
        $d = json_decode(html_entity_decode($m[1]), true);
        if (isset($d['component'])) {
            return $d['component'];
        }
    }
    return '(none)';
}

function props($body)
{
    if (preg_match('/data-page="([^"]+)"/', $body, $m)) {
        $d = json_decode(html_entity_decode($m[1]), true);
        return $d['props'] ?? [];
    }
    return [];
}

function firstId($body, $key)
{
    $p = props($body);
    if (!isset($p[$key])) {
        return null;
    }
    $v = $p[$key];
    if (isset($v['data'])) {
        $v = $v['data'];
    }
    if (is_array($v) && $v) {
        return $v[0]['id'] ?? null;
    }
    return null;
}

function check($label, $ok, $detail = '')
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "PASS  $label  $detail\n";
    } else {
        $fail++;
        echo "FAIL  $label  $detail\n";
    }
}

function login($email, $password)
{
    $jar = [];
    [, , , $jar] = grab('GET', '/login', $jar);
    [, , , $jar] = grab('POST', '/login', $jar, ['email' => $email, 'password' => $password], true);
    return $jar;
}

echo "=== 1) AUTH + LIST PAGES (admin@demo.com, seeded data) ===\n";
$jar = login('admin@demo.com', 'password');
[$st, $bd, , $jar] = grab('GET', '/dashboard', $jar);
check('GET /dashboard', $st == 200 && component($bd) == 'Dashboard', "(http $st, comp=" . component($bd) . ")");

$lists = [
    '/tickets' => ['Tickets/Index', 'tickets'],
    '/customers' => ['Customers/Index', 'customers'],
    '/devices' => ['Devices/Index', 'devices'],
    '/products' => ['Products/Index', 'products'],
    '/invoices' => ['Invoices/Index', 'invoices'],
];
$ids = [];
foreach ($lists as $path => [$expect, $key]) {
    [$st, $bd, , $jar] = grab('GET', $path, $jar);
    $comp = component($bd);
    $ids[$key] = firstId($bd, $key);
    check("GET $path", $st == 200 && str_contains($comp, $expect),
        "(http $st, comp=$comp, firstId=" . var_export($ids[$key], true) . ")");
}

echo "\n=== 2) DETAIL PAGES (seeded) ===\n";
$details = [
    '/tickets/' . ($ids['tickets'] ?? 0) => 'Tickets/Show',
    '/customers/' . ($ids['customers'] ?? 0) => 'Customers/Show',
    '/devices/' . ($ids['devices'] ?? 0) => 'Devices/Show',
    '/products/' . ($ids['products'] ?? 0) . '/edit' => 'Products/Edit',
    '/invoices/' . ($ids['invoices'] ?? 0) => 'Invoices/Show',
];
$demoTicketId = $ids['tickets'] ?? null;
foreach ($details as $path => $expect) {
    if (preg_match('/\/0(\/|$)/', $path)) {
        check("GET $path", false, '(no id available)');
        continue;
    }
    [$st, $bd, , $jar] = grab('GET', $path, $jar);
    $comp = component($bd);
    $ok = $st == 200 && str_contains($comp, $expect);
    $extra = '';
    if (str_ends_with($path, '/invoices/' . ($ids['invoices'] ?? 0))) {
        $p = props($bd);
        $extra = ' business=' . ($p['invoice']['business'] ?? '(missing)')
            . ' total=' . ($p['invoice']['total'] ?? '(missing)')
            . ' tasks=' . count($p['invoice']['tasks'] ?? [])
            . ' txns=' . count($p['invoice']['transactions'] ?? []);
    }
    check("GET $path", $ok, "(http $st, comp=$comp$extra)");
}

echo "\n=== 3) AUTH GATING (no session) ===\n";
foreach (['/dashboard', '/tickets', '/invoices', '/products'] as $path) {
    [$st, $bd, $loc] = grab('GET', $path, []);
    $gated = ($st == 302 && str_contains($loc ?? '', '/login')) || ($st == 200 && str_contains($bd, 'login'));
    check("GET $path logged out", $gated, "(http $st, loc=$loc)");
}

echo "\n=== 4) TENANT ISOLATION (Second Bird vs FixFlow Demo) ===\n";
$bird = login('manager@secondbird.test', 'password');
if ($demoTicketId) {
    [$st, $bd, $loc, $bird] = grab('GET', '/tickets/' . $demoTicketId, $bird);
    $comp = component($bd);
    $denied = ($st == 403) || ($st == 404);
    check('Second Bird opening a FixFlow Demo ticket', $denied,
        "(http $st, comp=$comp — must NOT render the ticket)");
} else {
    check('Second Bird opening a FixFlow Demo ticket', false, '(no demo ticket id)');
}
// Second Bird's own list must work and be its own tenant's data
[$st, $bd, , $bird] = grab('GET', '/tickets', $bird);
$p = props($bd);
$birdCount = count($p['tickets']['data'] ?? $p['tickets'] ?? []);
check('Second Bird sees own /tickets', $st == 200 && component($bd) == 'Tickets/Index',
    "(http $st, rows=$birdCount)");

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
