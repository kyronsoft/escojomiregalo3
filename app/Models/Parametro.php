<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Parametro
 *
 * @property int         $id
 * @property string      $nombre
 * @property string      $valor
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Parametro extends Model
{
    use HasFactory;

    /** Tabla asociada */
    protected $table = 'parametros';

    /** Clave primaria (opcional, Laravel asume 'id') */
    protected $primaryKey = 'id';

    /** Campos asignables en masa */
    protected $fillable = [
        'nombre',
        'valor',
    ];

    /** Casting de fechas */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Si quisieras helpers estáticos, puedes descomentar:
    // public static function getValor(string $nombre, $default = null)
    // {
    //     return static::query()->where('nombre', $nombre)->value('valor') ?? $default;
    // }
    //
    // public static function setValor(string $nombre, string $valor): self
    // {
    //     return static::updateOrCreate(['nombre' => $nombre], ['valor' => $valor]);
    // }
}
