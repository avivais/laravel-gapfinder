<?php

use Illuminate\Routing\Router;
use App\Http\Controllers\StockController;

/** @var Router $router */

$router->get('/', function () {
    dump("Welcome to the StockController");
});

$router->get('/stocks/{symbol}', [StockController::class, 'getStockData']);

$router->get('/stocks/{symbol}/pegs/{threshold}', [StockController::class, 'getStockPegs']);
