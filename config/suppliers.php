<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active parts supplier (default)
    |--------------------------------------------------------------------------
    |
    | The supplier used when a business has not chosen one of its own.
    | Suppliers are resolved through App\Suppliers\SupplierProviderRegistry.
    |
    |  - "ifixit"  -> App\Suppliers\Providers\IFixitProvider
    |                 Public iFixit API (https://www.ifixit.com/api/2.0). No
    |                 key required, so it is the default. Returns part/guide
    |                 discovery (title, link, category) -- the public API does
    |                 not expose price/stock, so those come back null.
    |
    |  - "manual"  -> App\Suppliers\Providers\ManualSupplierProvider
    |                 No integration. Guaranteed fallback so the supplier
    |                 settings page and search always work even if config is
    |                 broken or the network is down.
    |
    |  - "mobilesentrix" / "phonelcd" -> B2B partners. They are only usable
    |    once the shop sets the credentials under "gateways" (see below);
    |    until then isConfigured() is false and they fall back gracefully.
    |
    */

    'default' => env('SUPPLIER_PROVIDER', 'ifixit'),

    /*
    |--------------------------------------------------------------------------
    | Registered suppliers
    |--------------------------------------------------------------------------
    |
    | id => [label, class, description]. The label/description are surfaced in
    | the settings UI so a shop can pick where it sources parts. Add a new
    | entry here (plus its config under "gateways") to plug in another vendor.
    |
    */

    'providers' => [
        'ifixit' => [
            'label' => 'iFixit',
            'class' => \App\Suppliers\Providers\IFixitProvider::class,
            'description' => 'Search the public iFixit parts and repair-guides catalog. No API key needed.',
        ],
        'manual' => [
            'label' => 'Manual lookup',
            'class' => \App\Suppliers\Providers\ManualSupplierProvider::class,
            'description' => 'No supplier integration. Parts are looked up and ordered by hand -- always available.',
        ],
        'mobilesentrix' => [
            'label' => 'MobileSentrix',
            'class' => \App\Suppliers\Providers\MobileSentrixProvider::class,
            'description' => 'B2B parts supplier. Requires a partnership API key (see gateways config).',
        ],
        'phonelcd' => [
            'label' => 'PhoneLCD',
            'class' => \App\Suppliers\Providers\PhoneLcdProvider::class,
            'description' => 'B2B parts supplier. Requires a partnership API key (see gateways config).',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supplier credentials / endpoints
    |--------------------------------------------------------------------------
    |
    | Per-vendor settings for software-integrated suppliers. Values come from
    | environment variables so secrets never live in the repository. A supplier
    | provider is only "configured" when the keys it requires are present.
    |
    |   - "base_url"    : the supplier API root (partnership-specific).
    |   - "api_key"     : the secret token. Leave empty to disable the supplier.
    |   - "search_path" : path (relative to base_url) for a search, with {query}
    |                     and {limit} placeholders. Defaults to /search?q={query}&limit={limit}.
    |   - "auth_header" : the name of the header carrying the api_key.
    |
    | iFixit needs no credentials (public API); it only needs a base_url override
    | if you run a proxy.
    |
    */

    'gateways' => [
        'ifixit' => [
            'base_url' => env('IFIXIT_BASE_URL', 'https://www.ifixit.com/api/2.0'),
            // iFixit's search is a path parameter (/suggest/{query}), not a query
            // string. The public API has no parts catalog, so this returns
            // parts/repair-guide discovery only (no price/stock).
            'search_path' => env('IFIXIT_SEARCH_PATH', '/suggest/{query}'),
        ],
        'mobilesentrix' => [
            'base_url' => env('MOBILESENTRIX_BASE_URL'),
            'api_key' => env('MOBILESENTRIX_API_KEY'),
            'search_path' => env('MOBILESENTRIX_SEARCH_PATH', '/api/search?q={query}&limit={limit}'),
            'auth_header' => env('MOBILESENTRIX_AUTH_HEADER', 'Authorization'),
        ],
        'phonelcd' => [
            'base_url' => env('PHONELCD_BASE_URL'),
            'api_key' => env('PHONELCD_API_KEY'),
            'search_path' => env('PHONELCD_SEARCH_PATH', '/api/search?q={query}&limit={limit}'),
            'auth_header' => env('PHONELCD_AUTH_HEADER', 'X-Api-Key'),
        ],
    ],

];
