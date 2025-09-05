<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColaboradorHijo extends Model
{
    protected $table = 'colaborador_hijos';

    // Asegura que el campo se pueda asignar masivamente
    protected $fillable = [
        'identificacion',
        'nombre_hijo',
        'idcampaing',
        'genero',
        'rango_edad',
    ];

    // Lee siempre como entero (o null)
    protected $casts = [
        'rango_edad' => 'integer',
    ];

    /**
     * Normaliza y fuerza 0–14 o NULL al guardar, sin depender del import.
     */
    public function setRangoEdadAttribute($value): void
    {
        $this->attributes['rango_edad'] = self::normalizeEdad($value);
    }

    /**
     * Misma lógica de tu import, pero disponible en el modelo.
     */
    public static function normalizeEdad($value): ?int
    {
        if ($value === null) return null;

        // Excel puede traer número como int/float
        if (is_int($value) || is_float($value)) {
            $n = (int) floor($value);
            return $n < 0 ? 0 : ($n > 14 ? 14 : $n);
        }

        // String: extrae el primer número que aparezca ("7-9", "8 años", "12+")
        $s = trim((string) $value);
        if ($s === '') return null;

        if (preg_match('/\d+/', $s, $m)) {
            $n = (int) $m[0];
            if ($n < 0) $n = 0;
            if ($n > 14) $n = 14;
            return $n;
        }

        return null;
    }

    // belongsTo Colaborador por: identificacion -> colaboradores.documento
    public function colaborador()
    {
        return $this->belongsTo(Colaborador::class, 'identificacion', 'documento');
    }

    // belongsTo Campaign por: idcampaing -> campaigns.id
    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'idcampaing', 'id');
    }
}
