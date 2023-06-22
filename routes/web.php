<?php

use Illuminate\Routing\Router;
use App\Http\Controllers\StockController;

/** @var Router $router */

$router->get('/stocks/{symbol}', [StockController::class, 'getStockData']);
