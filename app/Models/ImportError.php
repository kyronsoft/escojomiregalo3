<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportError extends Model
{
    protected $table = 'importerrors';

    protected $fillable = [
        'row',
        'attribute',
        'errors',
        'values',
        'created_at',
    ];

    public $timestamps = false; // solo existe created_at en la tabla

    protected $casts = [
        'row'        => 'integer',
        'created_at' => 'datetime',
    ];
}
