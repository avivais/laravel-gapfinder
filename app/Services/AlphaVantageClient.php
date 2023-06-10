<?php

namespace App\Services;

use App\Models\StockPrice;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;

class AlphaVantageClient
{
    public const CACHE_KEY = 'last_request_time';

    public function __construct(
        private string $apiKey,
        private Client $client,
        private Cache $cache,
    ) {
    }

    public function getStockData(string $symbol): ?Collection
    {
        $this->checkRateLimit();

        $existingPrices = StockPrice::where('symbol', $symbol)->get();
        if ($existingPrices->isEmpty()) {
            // No existing data, fetch from API
            $timeSeries = $this->getStockDataFromAPI($symbol);

            $this->saveStockData($symbol, $timeSeries);

            return $timeSeries;
        } else {
            // Existing data found, fetch missing dates from API
            $latestDate = Carbon::parse($existingPrices->max('date'))->toDateString();

            $timeSeries = $this->getStockDataFromAPI($symbol, $latestDate);

            if (!$timeSeries) {
                return $existingPrices->sortBy('date');
            }

            // Store the stock data in the database for missing dates
            $missingDates = array_diff(
                $timeSeries->keys()->all(),
                $existingPrices->pluck('date')->map(function ($date) {
                    return Carbon::parse($date)->toDateString();
                })->toArray()
            );

            $stockPrices = $timeSeries
                ->filter(function ($values, $date) use ($missingDates) {
                    return in_array($date, $missingDates);
                })
                ->map(function (array $values, string $date) use ($symbol) {
                    return [
                        'symbol' => $symbol,
                        'date' => Carbon::parse($date)->toDateString(),
                        'open' => $values['1. open'],
                        'high' => $values['2. high'],
                        'low' => $values['3. low'],
                        'close' => $values['4. close'],
                        'volume' => $values['5. volume']
                    ];
                });

            $this->saveStockData($symbol, $stockPrices);

            // Merge existing and newly inserted prices
            return $existingPrices->concat($stockPrices);
        }
    }

    private function checkRateLimit()
    {
        $lastRequestTime = $this->cache->get(self::CACHE_KEY);
        $rateLimit = config('alphavantage.rate_limit');

        if ($lastRequestTime) {
            $elapsedTime = time() - $lastRequestTime;
            $remainingTime = max(0, $rateLimit - $elapsedTime);
            if ($remainingTime > 0) {
                sleep($remainingTime);
            }
        }

        // Update the last request time in the cache
        $this->cache->put(self::CACHE_KEY, time(), $rateLimit);
    }

    private function getStockDataFromAPI(string $symbol, ?string $startDate = null): ?Collection
    {
        $url = sprintf(
            "%s?apikey=%s&function=%s&symbol=%s",
            config('alphavantage.base_url'),
            config('alphavantage.api_key'),
            "TIME_SERIES_DAILY",
            $symbol
        );

        if ($startDate) {
            $url .= sprintf(
                "&startdate=%s&enddate=%s",
                $startDate,
                Carbon::today()->toDateString()
            );
        }
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

    private function saveStockData(string $symbol, Collection $timeSeries)
    {
        // Store the stock data in the database
        $stockPrices = collect($timeSeries)->map(function ($values, $date) use ($symbol) {
            return [
                'symbol' => $symbol,
                'date' => Carbon::parse($date)->toDateString(),
                'open' => $values['1. open'],
                'high' => $values['2. high'],
                'low' => $values['3. low'],
                'close' => $values['4. close'],
                'volume' => $values['5. volume']
            ];
        });

        // Insert new prices into the database
        StockPrice::insert($stockPrices->toArray());
    }
}
