<?php

use Illuminate\Routing\Router;
use App\Http\Controllers\StockController;

/** @var Router $router */

$router->get('/', function () {
    dump("Inserting to DB...");
});

$router->get('/stocks', [StockController::class, 'index']);
