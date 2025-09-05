<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorEmail extends Model
{
    protected $table = 'erroremail'; // nombre exacto de la tabla
    protected $primaryKey = 'id';
    public $timestamps = true; // usa created_at / updated_at

    protected $fillable = [
        'idcampaing',
        'documento',
        'email',
        'status',
        'created_at',
        'updated_at',
    ];
}
