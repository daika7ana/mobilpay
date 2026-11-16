<?php

namespace Adrianbarbos\Mobilpay;

use Illuminate\Support\ServiceProvider;

class MobilpayServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/mobilpay.php',
            'mobilpay',
        );

        $this->app->scoped(MobilpayGateway::class, function ($app) {
            return new MobilpayGateway();
        });

        $this->app->alias(MobilpayGateway::class, 'mobilpay');
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config' => config_path('/'),
        ]);
    }
}
