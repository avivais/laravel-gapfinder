<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class StockPrice extends Model
{
    protected $casts = [
        'date'   => 'datetime',
        'open'   => 'float',
        'high'   => 'float',
        'low'    => 'float',
        'close'  => 'float',
        'volume' => 'int',
    ];

    protected $fillable = [
        'symbol',
        'date',
        'open',
        'high',
        'low',
        'close',
        'volume',
    ];
}
