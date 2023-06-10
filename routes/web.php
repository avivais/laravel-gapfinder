<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;

/** @var Router $router */

$router->get('/', function () {
    dump("Inserting to DB...");

    $collection = DB::connection('mongodb')->collection('stock_data');
    dump($collection);
    $document = [
        'symbol' => 'test',
        'data' => ['a' => 1, 'b' => 2],
        'timestamp' => time()
    ];
    $collection->insert($document);
});
