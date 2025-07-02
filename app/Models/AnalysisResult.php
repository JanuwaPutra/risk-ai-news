<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisResult extends Model
{
    protected $fillable = [
        'nama',
        'jabatan',
        'paragraf',
        'source',
        'kata_kunci',
        'ringkasan',
        'skor_risiko',
        'persentase_kerawanan',
        'kategori',
        'faktor_risiko',
        'rekomendasi',
        'urgensi',
        'tanggal_tambah',
    ];

    protected $casts = [
        'faktor_risiko' => 'array',
        'tanggal_tambah' => 'datetime',
    ];

    public function setFaktorRisikoAttribute($value)
    {
        if (is_string($value)) {
            // Handle case where it comes as comma-separated string
            $this->attributes['faktor_risiko'] = json_encode(explode(',', $value));
        } else {
            $this->attributes['faktor_risiko'] = json_encode($value);
        }
    }

    public function getFaktorRisikoAttribute($value)
    {
        $decoded = json_decode($value, true);
        return $decoded ?: [];
    }
}
