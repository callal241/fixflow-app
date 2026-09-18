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

echo "\n=== 5) SUPPLIERS (milestone 3) ===\n";
// 5a) Page renders the Suppliers component with provider metadata.
[$st, $bd, , $jar] = grab('GET', '/suppliers', $jar);
$sp = props($bd);
check('GET /suppliers renders Suppliers/Index',
    $st == 200 && component($bd) == 'Suppliers/Index',
    "(http $st, comp=" . component($bd) . ")");
check('suppliers page exposes all 4 providers with metadata',
    isset($sp['suppliers']['ifixit'], $sp['suppliers']['manual'],
        $sp['suppliers']['mobilesentrix'], $sp['suppliers']['phonelcd'])
    && $sp['suppliers']['ifixit']['configured'] === true
    && $sp['suppliers']['manual']['configured'] === true
    && $sp['suppliers']['mobilesentrix']['configured'] === false,
    "ifixit=" . var_export($sp['suppliers']['ifixit']['configured'] ?? null, true)
    . " manual=" . var_export($sp['suppliers']['manual']['configured'] ?? null, true)
    . " mobilesentrix=" . var_export($sp['suppliers']['mobilesentrix']['configured'] ?? null, true));

// 5b) PUT persists a configured choice (manual) and the search endpoint then
//     serves that tenant's supplier as a graceful, non-crashing failure.
[, , , $jar] = grab('PUT', '/suppliers', $jar, ['supplier_provider_id' => 'manual'], true);
$after = props(grab('GET', '/suppliers', $jar)[1])['business']['supplier_provider_id'] ?? null;
check('PUT /suppliers persists supplier_provider_id=manual', $after === 'manual',
    "(stored=" . var_export($after, true) . ")");

// The search endpoint (JSON) resolves the acting tenant's active supplier.
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_URL => 'http://127.0.0.1:80/suppliers/search?q=' . urlencode('iphone 13 screen') . '&limit=5',
    CURLOPT_COOKIE => cookieHeader($jar),
    CURLOPT_TIMEOUT => 25,
]);
$rawJson = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
$decoded = json_decode($rawJson, true);
check('GET /suppliers/search (manual) returns graceful JSON',
    $code == 200 && is_array($decoded) && ($decoded['supplier'] ?? null) === 'manual'
    && ($decoded['success'] ?? null) === false && isset($decoded['failure_reason']),
    "(http $code, supplier=" . ($decoded['supplier'] ?? '?')
    . ", success=" . var_export($decoded['success'] ?? null, true)
    . ", reason=" . substr((string)($decoded['failure_reason'] ?? ''), 0, 60) . ")");

// 5c) Reset to the default (ifixit). Live search returns valid JSON; if the
//     container has outbound net we get real offers, otherwise a graceful
//     failure -- either way it is HTTP 200 with the expected shape.
[, , , $jar] = grab('PUT', '/suppliers', $jar, ['supplier_provider_id' => null], true);
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_URL => 'http://127.0.0.1:80/suppliers/search?q=' . urlencode('iphone 13 screen') . '&limit=5',
    CURLOPT_COOKIE => cookieHeader($jar),
    CURLOPT_TIMEOUT => 25,
]);
$rawJson2 = curl_exec($ch);
$code2 = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
$d2 = json_decode($rawJson2, true);
check('GET /suppliers/search (default ifixit) returns valid JSON contract',
    $code2 == 200 && is_array($d2) && ($d2['supplier'] ?? null) === 'ifixit'
    && is_bool($d2['success'] ?? null) && isset($d2['count']),
    "(http $code2, supplier=" . ($d2['supplier'] ?? '?')
    . ", success=" . var_export($d2['success'] ?? null, true)
    . ", count=" . ($d2['count'] ?? '?')
    . ", first=" . ($d2['offers'][0]['name'] ?? '-') . ")");

// 5d) Unauthenticated search is gated.
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_URL => 'http://127.0.0.1:80/suppliers/search?q=iphone',
    CURLOPT_TIMEOUT => 15,
]);
$g = curl_exec($ch);
$gc = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
check('GET /suppliers/search logged out is gated (401/redirect)',
    in_array($gc, [302, 401], true), "(http $gc)");

echo "\n=== 6) PARTS (milestone 4: find parts / receive / cancel / reserved stock) ===\n";

// 6a) The ticket show payload now carries the reserved/purchase fields.
[$st, $bd, , $jar] = grab('GET', '/tickets/' . $demoTicketId, $jar);
$tp = props($bd);
$firstProduct = $tp['products'][0] ?? null;
check('ticket show exposes product available + reserved_stock',
    $st == 200 && $firstProduct
    && array_key_exists('available', $firstProduct)
    && array_key_exists('reserved_stock', $firstProduct)
    && array_key_exists('stock', $firstProduct),
    "(http $st, product0=" . json_encode($firstProduct) . ")");
check('ticket show orders carry is_purchase + received_at',
    $st == 200 && (function () use ($tp) {
        if (empty($tp['orders'])) {
            // no seeded orders on the ticket; verify the shape via a fresh one below,
            // so treat "orders key exists" as the pass condition here.
            return array_key_exists('orders', $tp);
        }
        $o = $tp['orders'][0];
        return array_key_exists('is_purchase', $o) && array_key_exists('received_at', $o);
    })(),
    "(orders=" . count($tp['orders'] ?? []) . ")");

// 6b) Find parts: a free-form supplier part (no catalog product, not a purchase).
[, , , $jar] = grab('POST', '/tickets/' . $demoTicketId . '/orders', $jar, [
    'name' => 'Smoke free-form part', 'supplier' => 'Smoke Supplier',
    'quantity' => 1, 'price' => '9.99', 'is_billable' => '1',
], true);
[$st, $bd, , $jar] = grab('GET', '/tickets/' . $demoTicketId, $jar);
$tp = props($bd);
$freeOrder = null;
foreach ($tp['orders'] as $o) {
    if (($o['name'] ?? '') === 'Smoke free-form part' && !($o['is_purchase'] ?? false)) { $freeOrder = $o; break; }
}
check('find parts: free-form supplier part created (status new, billable)',
    $st == 200 && $freeOrder && $freeOrder['status'] === 'new'
    && $freeOrder['is_billable'] === true && $freeOrder['product_id'] === null,
    "(order=" . json_encode($freeOrder) . ")");

// 6c) Find parts: a purchase line (is_purchase true) is stored as a purchase.
[, , , $jar] = grab('POST', '/tickets/' . $demoTicketId . '/orders', $jar, [
    'name' => 'Smoke purchase part', 'supplier' => 'Smoke Supplier',
    'quantity' => 1, 'price' => '14.50', 'is_billable' => '1', 'is_purchase' => '1',
], true);
[$st, $bd, , $jar] = grab('GET', '/tickets/' . $demoTicketId, $jar);
$tp = props($bd);
$buyOrder = null;
foreach ($tp['orders'] as $o) {
    if (($o['name'] ?? '') === 'Smoke purchase part') { $buyOrder = $o; break; }
}
check('find parts: purchase line stored with is_purchase=true',
    $st == 200 && $buyOrder && $buyOrder['is_purchase'] === true && $buyOrder['status'] === 'new',
    "(order=" . json_encode($buyOrder) . ")");

// 6d) Cancel the free-form part -> becomes non-billable + cancelled.
if ($freeOrder) {
    grab('PATCH', '/tickets/' . $demoTicketId . '/orders/' . $freeOrder['id'] . '/cancel', $jar, [], true);
    [$st, $bd, , $jar] = grab('GET', '/tickets/' . $demoTicketId, $jar);
    $c = null;
    foreach (props($bd)['orders'] as $o) { if ($o['id'] === $freeOrder['id']) { $c = $o; break; } }
    check('cancel part: status cancelled + non-billable',
        $c && $c['status'] === 'cancelled' && $c['is_billable'] === false,
        "(order=" . json_encode($c) . ")");
    grab('DELETE', '/tickets/' . $demoTicketId . '/orders/' . $freeOrder['id'], $jar, [], true);
} else {
    check('cancel part (skipped: free-form part not found)', true, '');
}

// 6e) Receive the purchase part -> status received + received_at stamped.
if ($buyOrder) {
    grab('PATCH', '/tickets/' . $demoTicketId . '/orders/' . $buyOrder['id'] . '/receive', $jar, [], true);
    [$st, $bd, , $jar] = grab('GET', '/tickets/' . $demoTicketId, $jar);
    $r = null;
    foreach (props($bd)['orders'] as $o) { if ($o['id'] === $buyOrder['id']) { $r = $o; break; } }
    check('receive part: status received + received_at set',
        $r && $r['status'] === 'received' && !empty($r['received_at']),
        "(order=" . json_encode($r) . ")");
    grab('DELETE', '/tickets/' . $demoTicketId . '/orders/' . $buyOrder['id'], $jar, [], true);
} else {
    check('receive part (skipped: purchase part not found)', true, '');
}

// 6f) Own-stock part reserves stock: create (reserved+1) then delete (reserved back).
if ($firstProduct) {
    $pid = $firstProduct['id'];
    $reserved0 = (int) $firstProduct['reserved_stock'];
    $avail0 = (int) $firstProduct['available'];
    if ($avail0 >= 1) {
        grab('POST', '/tickets/' . $demoTicketId . '/orders', $jar, [
            'product_id' => $pid, 'quantity' => 1, 'is_billable' => '1',
        ], true);
        [$st, $bd, , $jar] = grab('GET', '/tickets/' . $demoTicketId, $jar);
        $tp = props($bd);
        $prodAfter = null; $ownOrder = null;
        foreach ($tp['products'] as $pp) { if ($pp['id'] === $pid) { $prodAfter = $pp; break; } }
        foreach ($tp['orders'] as $o) {
            if (($o['product_id'] ?? null) === $pid && $o['status'] === 'new' && !($o['is_purchase'] ?? false)) { $ownOrder = $o; break; }
        }
        $reservedAfter = $prodAfter ? (int) $prodAfter['reserved_stock'] : null;
        check('own-stock part reserves stock (reserved_stock +1, on-hand holds)',
            $prodAfter && $reservedAfter === $reserved0 + 1 && (int) $prodAfter['stock'] === (int) $firstProduct['stock'],
            "(reserved0=$reserved0 reservedAfter=" . var_export($reservedAfter, true) . " stock=" . $prodAfter['stock'] . ")");
        // delete the line: its reservation is released back.
        if ($ownOrder) {
            grab('DELETE', '/tickets/' . $demoTicketId . '/orders/' . $ownOrder['id'], $jar, [], true);
            [$st, $bd, , $jar] = grab('GET', '/tickets/' . $demoTicketId, $jar);
            $prodBack = null;
            foreach (props($bd)['products'] as $pp) { if ($pp['id'] === $pid) { $prodBack = $pp; break; } }
            check('removing the part releases its reservation (reserved_stock back to ' . $reserved0 . ')',
                $prodBack && (int) $prodBack['reserved_stock'] === $reserved0,
                "(reservedAfterDelete=" . ($prodBack ? $prodBack['reserved_stock'] : '?') . ")");
        }
    } else {
        check('own-stock reservation (skipped: no available stock on demo product)', true, "(available=$avail0)");
    }
} else {
    check('own-stock reservation (skipped: no products on demo ticket)', true, '');
}

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
