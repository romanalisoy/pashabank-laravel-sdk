<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Romanalisoy\PashaBank\Facades\PashaBank;
use Romanalisoy\PashaBank\PashaBankServiceProvider;

/**
 * Shared Testbench base for the SDK's own test suite. Registers the
 * provider, points the in-memory SQLite database at the package
 * migrations, and seeds a fake certificate path so MerchantConfig
 * validation passes without requiring a real .p12 on disk.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PashaBankServiceProvider::class];
    }

    /**
     * @param  Application  $app
     * @return array<string, string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'PashaBank' => PashaBank::class,
        ];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $certFixture = __DIR__.'/Fixtures/fake.p12';
        if (! is_dir(dirname($certFixture))) {
            mkdir(dirname($certFixture), 0777, true);
        }
        if (! file_exists($certFixture)) {
            file_put_contents($certFixture, 'not a real certificate — tests only');
        }

        // Testbench skips default app boot steps; set an explicit
        // encryption key so the `web` middleware (used by our callback
        // route) can decrypt cookies under test.
        $app['config']->set('app.key', 'base64:AckfSECXIvnK5r28GVIWUAxmbBSjTsmF11FwwSiC7UY=');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('pashabank.environment', 'testing');
        $app['config']->set('pashabank.endpoints.testing', [
            'merchant_handler' => 'https://bank.test/ecomm2/MerchantHandler',
            'client_handler' => 'https://bank.test/ecomm2/ClientHandler',
        ]);

        $app['config']->set('pashabank.merchants.main', [
            'merchant_id' => '0001234',
            'terminal_id' => 'TRM001',
            'certificate' => [
                'type' => 'pkcs12',
                'path' => $certFixture,
                'password' => 'test',
                'key_path' => null,
                'ca_path' => null,
            ],
            'language' => 'az',
            'currency' => 'AZN',
        ]);

        $app['config']->set('pashabank.http.verify_ssl', false);
        $app['config']->set('pashabank.logging.enabled', false);
    }
}
