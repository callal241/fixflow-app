<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active payment provider (default)
    |--------------------------------------------------------------------------
    |
    | The provider used when a business has not chosen one of its own.
    | Providers are resolved through App\Payments\PaymentProviderRegistry.
    |
    |  - "counter"  -> App\Payments\Providers\CounterTerminalProvider
    |                  Cash / card / online taken at the shop's own counter or
    |                  card readers & terminals. Always available, no keys.
    |
    |  - any other id must be registered in "providers" below and resolved to
    |    a concrete PaymentProvider class. Gateway providers (Stripe, Square,
    |    Adyen, …) read their credentials from the matching key in "gateways".
    |
    */

    'default' => env('PAYMENT_PROVIDER', 'counter'),

    /*
    |--------------------------------------------------------------------------
    | Registered providers
    |--------------------------------------------------------------------------
    |
    | id => [label, class, description]. The label/description are surfaced in
    | the settings UI so a shop can pick how it takes payments. Add a new entry
    | here (plus its config under "gateways") to plug in another vendor.
    |
    */

    'providers' => [
        'counter' => [
            'label' => 'Counter / card terminal',
            'class' => \App\Payments\Providers\CounterTerminalProvider::class,
            'description' => 'Cash and card taken at the counter or on your own readers and terminals.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway credentials
    |--------------------------------------------------------------------------
    |
    | Per-vendor settings for software-integrated gateways. Values come from
    | environment variables so secrets never live in the repository. A gateway
    | provider is only "configured" when the keys it requires are present.
    |
    | Example (Stripe):
    |     'stripe' => [
    |         'secret_key' => env('STRIPE_SECRET_KEY'),
    |         'mode' => env('STRIPE_MODE', 'test'),
    |     ],
    |
    */

    'gateways' => [
        // 'stripe' => ['secret_key' => env('STRIPE_SECRET_KEY')],
        // 'square' => ['access_token' => env('SQUARE_ACCESS_TOKEN'), 'location_id' => env('SQUARE_LOCATION_ID')],
    ],

];
