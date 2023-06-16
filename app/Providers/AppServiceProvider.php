<?php

namespace App\Providers;

use App\Services\AlphaVantageClient;
use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(AlphaVantageClient::class, function ($app) {
            $httpClient = $app->make(Client::class);
            $cache = $app->make(CacheRepository::class);
            $apiKey = config('services.alphavantage.api_key');
            $rateLimit = config('services.alphavantage.rate_limit');
            return new AlphaVantageClient($apiKey, $rateLimit, $httpClient, $cache);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
