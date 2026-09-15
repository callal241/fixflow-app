<?php

/**
 * PHPUnit bootstrap for FixFlow.
 *
 * Forces the testing environment into $_SERVER as well as $_ENV and putenv.
 *
 * Why: Laravel's phpdotenv reads $_SERVER BEFORE putenv/$_ENV. An image-level
 * Docker `ENV` (or any var inherited from the process) lands in $_SERVER and
 * would shadow phpunit.xml's `<env ... force="true">` overrides (which only
 * touch putenv/$_ENV). Setting $_SERVER here — before the framework boots —
 * makes the test env authoritative and independent of what the container
 * process happens to inherit. This is what makes `RefreshDatabase` hit the
 * in-memory DB and feature tests run with CSRF disabled.
 */

$testEnv = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'MAIL_MAILER' => 'array',
    'PULSE_ENABLED' => 'false',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'TELESCOPE_ENABLED' => 'false',
];

foreach ($testEnv as $key => $value) {
    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
    putenv("{$key}={$value}");
}

require __DIR__.'/../vendor/autoload.php';
