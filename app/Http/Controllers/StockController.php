<?php

namespace App\Http\Controllers;

use App\Services\AlphaVantageClient;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private AlphaVantageClient $alphaVantageClient)
    {
    }

    public function getStockData(Request $request, string $symbol)
    {
        $stockData = $this->alphaVantageClient->getStockData($symbol);

        if ($stockData->isEmpty()) {
            return redirect()->back()->with('error', 'No stock data available for the symbol.');
        }

        return response()->json(['data' => $stockData]);
    }
}
