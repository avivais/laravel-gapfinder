<?php

namespace App\Http\Controllers;

use App\Services\AlphaVantageClient;
use Exception;

class StockController extends Controller
{
    public function index(AlphaVantageClient $client)
    {
        try {
            $symbol = 'AAPL';
            $stockData = $client->getStockData($symbol);
            dd($stockData);

            // Display the stock data
            if ($stockData) {
                foreach ($stockData as $date => $values) {
                    echo "Date: $date\n";
                    echo "Open: {$values['1. open']}\n";
                    echo "High: {$values['2. high']}\n";
                    echo "Low: {$values['3. low']}\n";
                    echo "Close: {$values['4. close']}\n";
                    echo "Volume: {$values['6. volume']}\n";
                    echo "-------------\n";
                }
            } else {
                echo "Failed to retrieve stock data for $symbol.\n";
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
