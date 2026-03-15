<?php

namespace App\Providers;

use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\Gateway\HttpMailGatewayClient;
use App\Services\Mailing\Gateway\StubMailGatewayClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MailGatewayClient::class, function () {
            return match (config('mailing.gateway.driver')) {
                'http' => new HttpMailGatewayClient(),
                default => new StubMailGatewayClient(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
