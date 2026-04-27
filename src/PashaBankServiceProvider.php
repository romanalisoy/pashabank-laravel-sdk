<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Romanalisoy\PashaBank\Client\EcommClient;

/**
 * Wires the SDK into Laravel:
 *   - merges + publishes config
 *   - registers EcommClient and PashaBankManager as singletons
 *   - publishes migrations (guarded by persistence.enabled)
 *   - loads the callback route (if enabled)
 */
final class PashaBankServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pashabank.php', 'pashabank');

        $this->app->singleton(EcommClient::class, function ($app): EcommClient {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            /** @var array{timeout?: int, connect_timeout?: int, verify_ssl?: bool, verify_host?: bool, return_transfer?: bool, tls_version?: string} $http */
            $http = (array) $config->get('pashabank.http', []);

            /** @var array{enabled?: bool, channel?: string, mask_card_numbers?: bool} $logging */
            $logging = (array) $config->get('pashabank.logging', []);

            return new EcommClient(
                http: $app->make(HttpFactory::class),
                httpConfig: [
                    'timeout' => (int) ($http['timeout'] ?? 30),
                    'connect_timeout' => (int) ($http['connect_timeout'] ?? 10),
                    'verify_ssl' => (bool) ($http['verify_ssl'] ?? true),
                    'verify_host' => (bool) ($http['verify_host'] ?? true),
                    'return_transfer' => (bool) ($http['return_transfer'] ?? true),
                    'tls_version' => (string) ($http['tls_version'] ?? 'TLSv1.2'),
                ],
                loggingConfig: [
                    'enabled' => (bool) ($logging['enabled'] ?? true),
                    'channel' => (string) ($logging['channel'] ?? 'stack'),
                    'mask_card_numbers' => (bool) ($logging['mask_card_numbers'] ?? true),
                ],
            );
        });

        $this->app->singleton(PashaBankManager::class, function ($app): PashaBankManager {
            return new PashaBankManager(
                config: $app->make(ConfigRepository::class),
                client: $app->make(EcommClient::class),
                events: $app->make(Dispatcher::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/pashabank.php' => config_path('pashabank.php'),
        ], ['pashabank', 'pashabank-config']);

        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        if ((bool) $config->get('pashabank.persistence.enabled', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], ['pashabank-migrations']);
        }

        if ((bool) $config->get('pashabank.callback.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/pashabank.php');
        }
    }
}
