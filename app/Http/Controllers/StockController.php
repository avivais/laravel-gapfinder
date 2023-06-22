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
        $data = $this->alphaVantageClient->getStockData($symbol);

        if ($data === null) {
            return response()->json(['message' => "No stock data found for {$symbol}"], 404);
        }

        return response()->json(['data' => $data]);
    }
}
