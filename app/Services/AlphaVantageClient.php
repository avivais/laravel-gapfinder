<?php

namespace App\Services;

use App\Models\StockPrice;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Exception;

class AlphaVantageClient
{
    public const CACHE_KEY = 'last_request_time';

    public function __construct(
        private string $apiKey,
        private int $rateLimit,
        private Client $client,
        private CacheRepository $cache,
    ) {
    }

    public function getStockData(string $symbol): ?Collection
    {
        $this->checkRateLimit();

        $existingPrices = StockPrice::where('symbol', $symbol)->get();

        $apiData = $this->getStockDataFromAPI($symbol);

        if ($existingPrices->isEmpty()) {
            if ($apiData->isEmpty()) {
                return null;
            }

            $this->saveStockData($symbol, $apiData);
        }

        // Existing data found, add missing dates from API
        if ($apiData->isNotEmpty()) {
            // Store the stock data in the database for missing dates
            $missingDates = $apiData->keys()->diff(
                $existingPrices
                    ->pluck('date')
                    ->map(fn(string $date) => Carbon::parse($date)->toDateString())
            );

            if ($missingDates->isNotEmpty()) {
                $stockPrices = $apiData
                    ->filter(fn(array $values, string $date) => $missingDates->contains($date));

                $this->saveStockData($symbol, $stockPrices);
            }
        }

        return StockPrice::where('symbol', $symbol)->orderBy('date')->get();
    }

    private function checkRateLimit()
    {
        $lastRequestTime = $this->cache->get(self::CACHE_KEY);

        if ($lastRequestTime) {
            $elapsedTime = time() - $lastRequestTime;
            $remainingTime = max(0, $this->rateLimit - $elapsedTime);
            if ($remainingTime > 0) {
                sleep($remainingTime);
            }
        }

        // Update the last request time in the cache
        $this->cache->put(self::CACHE_KEY, time(), $this->rateLimit);
    }

    private function getStockDataFromAPI(string $symbol): ?Collection
    {
        $url = sprintf(
            "%s?apikey=%s&function=%s&symbol=%s&outputsize=compact",
            config('services.alphavantage.base_url'),
            $this->apiKey,
            "TIME_SERIES_DAILY_ADJUSTED",
            $symbol
        );

        try {
            // Make the API request
            $response = $this->client->get($url);
            $data = json_decode($response->getBody(), true);

            if (isset($data['Error Message'])) {
                throw new Exception("Failed to retrieve stock data for {$symbol}. Error: {$data['Error Message']}");
            } elseif (isset($data['Time Series (Daily)'])) {
                return collect($data['Time Series (Daily)']);
            } else {
                return null;
            }
        } catch (Exception $e) {
            throw new Exception("Failed to retrieve stock data for $symbol. Error: {$e->getMessage()}");
        }
    }

    private function saveStockData(string $symbol, Collection $data)
    {
        // Store the stock data in the database
        $stockPrices = $data->map(
            fn(array $values, string $date) => StockPrice::create([
                'symbol' => $symbol,
                'date' => Carbon::parse($date)->startOfDay(),
                'open' => $values['1. open'],
                'high' => $values['2. high'],
                'low' => $values['3. low'],
                'close' => $values['4. close'],
                'volume' => $values['6. volume']
            ])
        );
    }
}
