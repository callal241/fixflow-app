<?php

use App\Exceptions\UnknownSupplierException;
use App\Models\Business;
use App\Models\User;
use App\Suppliers\PartOffer;
use App\Suppliers\PartSearchResult;
use App\Suppliers\Providers\IFixitProvider;
use App\Suppliers\Providers\MobileSentrixProvider;
use App\Suppliers\Providers\PhoneLcdProvider;
use App\Suppliers\SupplierProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->user = User::factory()->admin()->create(['business_id' => $this->business->id]);
});

/*
|--------------------------------------------------------------------------
| A tiny local "supplier" server that serves a recorded iFixit fixture.
| This lets the tests exercise the full HTTP path of HttpSupplierProvider
| (curl -> status code -> json_decode -> parseResponse) without any live
| network dependency. The process is spawned per test file run and killed
| in afterEach.
|--------------------------------------------------------------------------
*/
$fixtureServer = null;

afterEach(function () use (&$fixtureServer) {
    if ($fixtureServer !== null) {
        $fixtureServer->stop();
        $fixtureServer = null;
    }
});

function startFixtureServer(array $fixture, int $status = 200): array
{
    $dir = sys_get_temp_dir() . '/fixflow_fixture_' . getmypid();
    @mkdir($dir, 0777, true);
    file_put_contents($dir . '/fixture.json', json_encode($fixture));

    $script = $dir . '/serve.php';
    file_put_contents($script, <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$dir = __DIR__;
if ($path === '/ok') {
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode(['results' => []]);
    return;
}
if ($path === '/err') {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'boom']);
    return;
}
if ($path === '/notjson') {
    header('Content-Type: text/plain');
    http_response_code(200);
    echo 'not json at all';
    return;
}
// Default: serve the fixture as the "search" response for any path.
header('Content-Type: application/json');
echo file_get_contents($dir . '/fixture.json');
PHP);

    $proc = new Process(['php', '-S', '127.0.0.1:18421', '-t', $dir, $script]);
    $proc->start();

    // Wait for the server to accept connections (up to ~3s).
    $up = false;
    for ($i = 0; $i < 30; $i++) {
        $sock = @fsockopen('127.0.0.1', 18421, $errno, $errstr, 0.2);
        if ($sock !== false) {
            fclose($sock);
            $up = true;
            break;
        }
        usleep(100_000);
    }
    if (! $up) {
        $proc->stop();
        throw new RuntimeException('fixture server did not come up');
    }

    return [$proc, 'http://127.0.0.1:18421'];
}

function ifixitFixture(): array
{
    // Recorded shape from the real iFixit /suggest/{query} endpoint (2026-08).
    return [
        'query' => 'iphone 13 screen',
        'results' => [
            [
                'dataType' => 'guide',
                'guideid' => 145897,
                'url' => 'https://www.ifixit.com/Guide/iPhone+13+Screen+Replacement/145897',
                'type' => 'replacement',
                'category' => 'iPhone 13',
                'subject' => 'Screen',
                'title' => 'iPhone 13 Screen Replacement',
                'summary' => 'If your iPhone 13 screen is cracked, not...',
                'image' => [
                    'standard' => 'https://guide-images.cdn.ifixit.com/igi/abc.standard',
                    'thumbnail' => 'https://guide-images.cdn.ifixit.com/igi/abc.thumbnail',
                ],
            ],
            [
                'dataType' => 'part',
                'partid' => 998877,
                'url' => 'https://www.ifixit.com/Part/iPhone+13+LCD/998877',
                'category' => 'iPhone 13',
                'title' => 'iPhone 13 LCD Display Assembly',
                'image' => ['thumbnail' => 'https://guide-images.cdn.ifixit.com/igi/def.thumbnail'],
            ],
        ],
    ];
}

/*
|--------------------------------------------------------------------------
| Registry
|--------------------------------------------------------------------------
*/
test('the supplier registry resolves the configured default for a business with no choice', function () {
    $registry = app(SupplierProviderRegistry::class);

    expect($registry->for($this->business)->id())->toBe('ifixit') // default from config
        ->and($registry->for(null)->id())->toBe('ifixit');
});

test('the supplier registry honours a business choice that is registered and configured', function () {
    $this->business->forceFill(['supplier_provider_id' => 'manual'])->save();

    expect(app(SupplierProviderRegistry::class)->for($this->business->fresh())->id())->toBe('manual');
});

test('the supplier registry falls back to the default when the business points at an unconfigured provider', function () {
    // mobilesentrix has no credentials in test config -> not configured.
    $this->business->forceFill(['supplier_provider_id' => 'mobilesentrix'])->save();

    expect(app(SupplierProviderRegistry::class)->for($this->business->fresh())->id())->toBe('ifixit');
});

test('the supplier registry falls back to the default when the business points at an unknown provider', function () {
    $this->business->forceFill(['supplier_provider_id' => 'ghost'])->save();

    expect(app(SupplierProviderRegistry::class)->for($this->business->fresh())->id())->toBe('ifixit');
});

test('the registry resolves null for unknown ids so callers can fall back', function () {
    $registry = app(SupplierProviderRegistry::class);

    expect($registry->resolve('does-not-exist'))->toBeNull()
        ->and($registry->resolve(null))->toBeNull()
        ->and($registry->resolve('ifixit') instanceof IFixitProvider)->toBeTrue()
        ->and($registry->resolve('manual')->id())->toBe('manual');
});

test('the registry throws for a registered id whose class does not implement the contract', function () {
    config(['suppliers.providers.broken' => ['label' => 'Broken', 'class' => \stdClass::class]]);

    $this->expectException(UnknownSupplierException::class);
    app(SupplierProviderRegistry::class)->resolve('broken');
});

test('the registry options expose label, description, configured flag and hint for every provider', function () {
    $options = app(SupplierProviderRegistry::class)->options();

    expect($options)
        ->toHaveKeys(['ifixit', 'manual', 'mobilesentrix', 'phonelcd'])
        ->and($options['ifixit']['configured'])->toBeTrue()
        ->and($options['manual']['configured'])->toBeTrue()
        ->and($options['mobilesentrix']['configured'])->toBeFalse()
        ->and($options['phonelcd']['configured'])->toBeFalse()
        ->and($options['mobilesentrix']['hint'])->toContain('MOBILESENTRIX_API_KEY')
        ->and($options['manual']['label'])->toBe('Manual lookup');
});

/*
|--------------------------------------------------------------------------
| iFixit provider: real HTTP path against a recorded fixture
|--------------------------------------------------------------------------
*/
test('ifixit search hits the HTTP endpoint and normalizes offers without inventing price or stock', function () use (&$fixtureServer) {
    [$proc, $base] = startFixtureServer(ifixitFixture());
    $fixtureServer = $proc;
    config(['suppliers.gateways.ifixit.base_url' => $base]);

    $provider = app(SupplierProviderRegistry::class)->resolve('ifixit');
    $result = $provider->searchParts('iphone 13 screen', 10);

    expect($result->success)->toBeTrue()
        ->and($result->statusCode)->toBe(200)
        ->and($result->offers)->toHaveCount(2);

    $first = $result->offers[0];
    expect($first)->toBeInstanceOf(PartOffer::class)
        ->and($first->supplier)->toBe('ifixit')
        ->and($first->name)->toBe('iPhone 13 Screen Replacement')
        ->and($first->url)->toBe('https://www.ifixit.com/Guide/iPhone+13+Screen+Replacement/145897')
        ->and($first->category)->toBe('iPhone 13')
        ->and($first->externalId)->toBe('145897')
        ->and($first->image)->toBe('https://guide-images.cdn.ifixit.com/igi/abc.standard')
        ->and($first->price)->toBeNull()
        ->and($first->stock)->toBeNull()
        ->and($first->hasPrice())->toBeFalse();

    $second = $result->offers[1];
    expect($second->name)->toBe('iPhone 13 LCD Display Assembly')
        ->and($second->externalId)->toBe('998877');
});

test('ifixit search with an empty query fails without touching the network', function () {
    $provider = app(SupplierProviderRegistry::class)->resolve('ifixit');

    $result = $provider->searchParts('   ');

    expect($result->success)->toBeFalse()
        ->and($result->failureReason)->toContain('empty');
});

test('ifixit search surfaces a non-2xx upstream response as a failure with status code', function () use (&$fixtureServer) {
    [$proc, $base] = startFixtureServer([]);
    $fixtureServer = $proc;
    config(['suppliers.gateways.ifixit' => [
        'base_url' => $base,
        'search_path' => '/err',
    ]]);

    $result = app(SupplierProviderRegistry::class)->resolve('ifixit')->searchParts('anything');

    expect($result->success)->toBeFalse()
        ->and($result->statusCode)->toBe(500)
        ->and($result->failureReason)->toContain('500');
});

test('ifixit search surfaces a non-JSON upstream body as a failure', function () use (&$fixtureServer) {
    [$proc, $base] = startFixtureServer([]);
    $fixtureServer = $proc;
    config(['suppliers.gateways.ifixit' => [
        'base_url' => $base,
        'search_path' => '/notjson',
    ]]);

    $result = app(SupplierProviderRegistry::class)->resolve('ifixit')->searchParts('anything');

    expect($result->success)->toBeFalse()
        ->and($result->failureReason)->toContain('non-JSON');
});

/*
|--------------------------------------------------------------------------
| B2B providers (MobileSentrix / PhoneLCD): credential-driven, no network
|--------------------------------------------------------------------------
*/
test('B2B suppliers report unconfigured until base_url and api_key are set', function () {
    $ms = app(SupplierProviderRegistry::class)->resolve('mobilesentrix');
    $pl = app(SupplierProviderRegistry::class)->resolve('phonelcd');

    expect($ms)->toBeInstanceOf(MobileSentrixProvider::class)
        ->and($pl)->toBeInstanceOf(PhoneLcdProvider::class)
        ->and($ms->isConfigured())->toBeFalse()
        ->and($pl->isConfigured())->toBeFalse();

    // Searching while unconfigured returns a clear failure, never throws.
    $msResult = $ms->searchParts('iphone screen');
    expect($msResult->success)->toBeFalse()
        ->and($msResult->failureReason)->toContain('MOBILESENTRIX_BASE_URL')
        ->and($msResult->failureReason)->toContain('MOBILESENTRIX_API_KEY');
});

test('B2B suppliers become configured when credentials are present', function () {
    config([
        'suppliers.gateways.mobilesentrix.base_url' => 'https://partner.mobilesentrix.example',
        'suppliers.gateways.mobilesentrix.api_key' => 'test-key',
    ]);

    $ms = app(SupplierProviderRegistry::class)->resolve('mobilesentrix');
    expect($ms->isConfigured())->toBeTrue()
        ->and($ms->configurationHint())->toBeNull();
});

test('B2B supplier maps a generic catalog payload onto offers', function () {
    config([
        'suppliers.gateways.mobilesentrix.base_url' => 'https://partner.mobilesentrix.example',
        'suppliers.gateways.mobilesentrix.api_key' => 'test-key',
    ]);

    // Call the pure parse path directly with a representative payload.
    $provider = app(SupplierProviderRegistry::class)->resolve('mobilesentrix');
    $parse = new ReflectionMethod(MobileSentrixProvider::class, 'parseResponse');
    $offers = $parse->invoke($provider, [
        'results' => [
            ['name' => 'iPhone 13 Screen', 'sku' => 'MS-13', 'price' => 42.5, 'stock' => 7, 'url' => 'https://partner/mobilesentrix.example/p/13', 'image' => 'https://partner/mobilesentrix.example/i/13.png'],
            ['title' => 'Galaxy S23 Battery', 'part_number' => 'GS23-BAT', 'unit_price' => 18.0, 'in_stock' => 3],
            ['id' => 42], // no usable name -> dropped
        ],
    ]);

    expect($offers)->toHaveCount(2)
        ->and($offers[0]->name)->toBe('iPhone 13 Screen')
        ->and($offers[0]->externalId)->toBe('MS-13')
        ->and($offers[0]->price)->toBe(42.5)
        ->and($offers[0]->stock)->toBe(7)
        ->and($offers[0]->hasPrice())->toBeTrue()
        ->and($offers[1]->name)->toBe('Galaxy S23 Battery')
        ->and($offers[1]->externalId)->toBe('GS23-BAT')
        ->and($offers[1]->price)->toBe(18.0);
});

/*
|--------------------------------------------------------------------------
| PartOffer / PartSearchResult value objects
|--------------------------------------------------------------------------
*/
test('part offers and search results serialize to stable payloads', function () {
    $offer = new PartOffer(
        supplier: 'ifixit',
        name: 'Test Part',
        externalId: 'X-1',
        url: 'https://example.com/x-1',
        price: 9.99,
        stock: 4,
    );

    expect($offer->toPayload())->toBe([
        'supplier' => 'ifixit',
        'name' => 'Test Part',
        'external_id' => 'X-1',
        'url' => 'https://example.com/x-1',
        'image' => null,
        'price' => 9.99,
        'stock' => 4,
        'category' => null,
        'description' => null,
    ]);

    $result = PartSearchResult::success('ifixit', [$offer], 200);
    expect($result->toPayload())
        ->toBe([
            'supplier' => 'ifixit',
            'success' => true,
            'offers' => [$offer->toPayload()],
            'count' => 1,
            'failure_reason' => null,
            'status_code' => 200,
        ]);

    $failure = PartSearchResult::failure('manual', 'no integration', null);
    expect($failure->toPayload()['success'])->toBeFalse()
        ->and($failure->toPayload()['failure_reason'])->toBe('no integration')
        ->and($failure->toPayload()['offers'])->toBe([]);
});

/*
|--------------------------------------------------------------------------
| HTTP endpoints: suppliers page, search, update; tenant scoping + auth
|--------------------------------------------------------------------------
*/
test('the suppliers page renders with provider metadata and the active supplier', function () {
    $this->business->forceFill(['supplier_provider_id' => 'manual'])->save();

    $this->actingAs($this->user)
        ->get(route('suppliers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Suppliers/Index')
            ->has('suppliers.ifixit')
            ->has('suppliers.manual')
            ->has('suppliers.mobilesentrix')
            ->has('suppliers.phonelcd')
            ->where('suppliers.ifixit.configured', true)
            ->where('suppliers.mobilesentrix.configured', false)
            ->where('active_supplier_id', 'manual')
            ->where('business.supplier_provider_id', 'manual')
        );
});

test('the parts search endpoint requires authentication', function () {
    $this->get(route('suppliers.search'), ['q' => 'iphone'])
        ->assertRedirect(route('login'));
});

test('the parts search endpoint rejects a missing query', function () {
    $this->actingAs($this->user)
        ->getJson(route('suppliers.search'))
        ->assertStatus(422);
});

test('the parts search endpoint searches the business active supplier (manual fallback) without network', function () {
    // No supplier_provider_id and default is ifixit -- but point the business
    // at manual so no HTTP is attempted at all.
    $this->business->forceFill(['supplier_provider_id' => 'manual'])->save();

    $this->actingAs($this->user)
        ->getJson(route('suppliers.search') . '?q=' . urlencode('iphone 13 screen'))
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('supplier', 'manual')
        ->assertJsonPath('count', 0);
});

test('the parts search endpoint uses the live default supplier (ifixit) via HTTP fixture', function () use (&$fixtureServer) {
    [$proc, $base] = startFixtureServer(ifixitFixture());
    $fixtureServer = $proc;
    config(['suppliers.gateways.ifixit.base_url' => $base]);

    $this->actingAs($this->user)
        ->getJson(route('suppliers.search') . '?q=' . urlencode('iphone 13 screen') . '&limit=10')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('supplier', 'ifixit')
        ->assertJsonPath('count', 2)
        ->assertJsonPath('offers.0.name', 'iPhone 13 Screen Replacement')
        ->assertJsonPath('offers.0.price', null);
});

test('a supplier search by one business cannot see another business supplier choice', function () {
    // Business A uses manual, business B uses default (ifixit fixture).
    $otherBusiness = Business::factory()->create();
    $otherUser = User::factory()->admin()->create(['business_id' => $otherBusiness->id]);
    $this->business->forceFill(['supplier_provider_id' => 'manual'])->save();

    // A searches: manual failure, no network.
    $this->actingAs($this->user)
        ->getJson(route('suppliers.search') . '?q=' . urlencode('test part'))
        ->assertJsonPath('supplier', 'manual');

    // B searches: still its own default.
    $this->actingAs($otherUser)
        ->getJson(route('suppliers.search') . '?q=' . urlencode('test part'))
        ->assertJsonPath('supplier', 'ifixit');
});

test('business settings persist the supplier choice and normalise unconfigured ids to null', function () {
    // Configured id is stored.
    $this->actingAs($this->user)->put(route('suppliers.update'), [
        'supplier_provider_id' => 'manual',
    ])->assertRedirect(route('suppliers.index'));
    expect($this->business->fresh()->supplier_provider_id)->toBe('manual');

    // Configured default id is stored.
    $this->actingAs($this->user)->put(route('suppliers.update'), [
        'supplier_provider_id' => 'ifixit',
    ]);
    expect($this->business->fresh()->supplier_provider_id)->toBe('ifixit');

    // Unconfigured B2B id is normalised to null (use default).
    $this->actingAs($this->user)->put(route('suppliers.update'), [
        'supplier_provider_id' => 'mobilesentrix',
    ]);
    expect($this->business->fresh()->supplier_provider_id)->toBeNull();

    // Unknown id is normalised to null.
    $this->actingAs($this->user)->put(route('suppliers.update'), [
        'supplier_provider_id' => 'ghost',
    ]);
    expect($this->business->fresh()->supplier_provider_id)->toBeNull();

    // Explicit null clears the choice.
    // Fresh user instance: a real request resolves a fresh user each time, so
    // the user->business relationship is not cached from the puts above.
    $this->business->forceFill(['supplier_provider_id' => 'manual'])->save();
    $clearUser = User::factory()->admin()->create(['business_id' => $this->business->id]);
    $this->actingAs($clearUser)->put(route('suppliers.update'), [
        'supplier_provider_id' => null,
    ]);
    expect($this->business->fresh()->supplier_provider_id)->toBeNull();
});

test('updating the supplier requires authentication', function () {
    $this->put(route('suppliers.update'), ['supplier_provider_id' => 'manual'])
        ->assertRedirect(route('login'));
});
