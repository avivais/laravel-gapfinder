<?php

namespace App\Http\Controllers;

use App\Services\AlphaVantageClient;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private AlphaVantageClient $alphaVantageClient)
    {
    }

    public function getStockData(Request $request, string $symbol): View
    {
        try {
            $stockData = $this->alphaVantageClient->getStockData($symbol);

            if ($stockData->isEmpty()) {
                throw new Exception("No stock data available for {$symbol}");
            }

            return view('stock', compact('stockData', 'symbol'));
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            return view('error', compact('errorMessage'));
        }
    }

    public function getStockPegs(Request $request, string $symbol, float $threshold): View
    {
        try {
            $stockPegs = $this->alphaVantageClient->findPowerEarningsGaps($symbol, $threshold);
            dd($stockPegs);

            if ($stockPegs->isEmpty()) {
                throw new Exception("No PEGs with gap >= {$threshold} found for {$symbol}");
            }

            return view('stock', compact('stockPegs', 'symbol', 'threshold'));
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            return view('error', compact('errorMessage'));
        }
    }
}
