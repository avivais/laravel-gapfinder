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

        if ($existingPrices->isEmpty() && $apiData->isEmpty()) {
            return null;
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

        return StockPrice::where('symbol', $symbol)->orderByDesc('date')->get();
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
            "%s?apikey=%s&function=%s&symbol=%s&outputsize=full",
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
                throw new Exception("Failed to retrieve stock data for {$symbol}: {$data['Error Message']}");
            } elseif (isset($data['Time Series (Daily)'])) {
                return collect($data['Time Series (Daily)']);
            } else {
                throw new Exception("Invalid API response for {$symbol}");
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function saveStockData(string $symbol, Collection $data)
    {
        // Store the stock data in the database
        $data->each(
            fn(array $values, string $date) => StockPrice::create([
                'symbol' => $symbol,
                'date'   => Carbon::parse($date)->startOfDay(),
                'open'   => floatval($values['1. open']),
                'high'   => floatval($values['2. high']),
                'low'    => floatval($values['3. low']),
                'close'  => floatval($values['4. close']),
                'volume' => intval($values['6. volume']),
            ])
        );
    }

    private function getQuarterlyReportsFromAPI(string $symbol): ?Collection
    {
        $url = sprintf(
            "%s?apikey=%s&function=%s&symbol=%s",
            config('services.alphavantage.base_url'),
            $this->apiKey,
            "EARNINGS",
            $symbol
        );

        try {
            // Make the API request
            $response = $this->client->get($url);
            $data = json_decode($response->getBody(), true);

            if (isset($data['Error Message'])) {
                throw new Exception("Failed to retrieve annual reports for {$symbol}: {$data['Error Message']}");
            } elseif (isset($data['quarterlyEarnings'])) {
                return collect($data['quarterlyEarnings']);
            } else {
                throw new Exception("Invalid API response for {$symbol}");
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function findSignificantGaps(string $symbol, float $threshold): Collection
    {
        $prices = StockPrice::where('symbol', $symbol)->orderBy('date')->get();

        return $prices
            ->zip($prices->skip(1))->filter(function ($pair) use ($threshold) {
                [$previousPrice, $currentPrice] = $pair;

                // When there are no more pairs, $currentPrice will be null
                if (!$currentPrice) {
                    return false;
                }

                return (($currentPrice->open - $previousPrice->close) / $previousPrice->close) >= $threshold;
            })
            ->map(function ($pair) {
                [$previousPrice, $currentPrice] = $pair;

                return [
                    'previous' => $previousPrice,
                    'current'  => $currentPrice,
                ];
            });
    }

    public function findPowerEarningsGaps(string $symbol, float $threshold): Collection
    {
        $gaps = $this->findSignificantGaps($symbol, $threshold);
        $reports = $this->getQuarterlyReportsFromAPI($symbol);

        $reportDates = $reports->pluck('reportedDate')->map(function ($date) {
            return Carbon::parse($date);
        });

        return $gaps
            ->map(function ($gap) use ($reportDates, $symbol) {
                $gapDate = $gap['current']->date;

                $reportDateOneDayBefore = $reportDates->first(function ($reportDate) use ($gapDate) {
                    return $reportDate->copy()->addDay()->equalTo($gapDate);
                });

                $reportDateTwoDaysBefore = $reportDates->first(function ($reportDate) use ($gapDate) {
                    return $reportDate->copy()->addDays(2)->equalTo($gapDate);
                });

                if ($reportDateOneDayBefore || $reportDateTwoDaysBefore) {
                    $gap['reportDate'] = $reportDateOneDayBefore ?: $reportDateTwoDaysBefore;

                    if ($reportDateTwoDaysBefore) {
                        $gap['twodaysago'] = StockPrice::where('symbol', $symbol)
                            ->where('date', $reportDateTwoDaysBefore)
                            ->get()
                            ->first();
                    }
                }

                return $gap;
            })
            ->filter(function ($gap) {
                return isset($gap['reportDate']);
            });
    }
}
