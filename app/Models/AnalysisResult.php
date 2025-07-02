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
        'url',
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
    
    /**
     * Boot function to add model events
     */
    protected static function boot()
    {
        parent::boot();
        
        // Before saving, automatically determine urgency level
        static::saving(function ($model) {
            $model->urgensi = self::determineUrgencyFromCategory($model->kategori, $model->skor_risiko);
        });
    }
    
    /**
     * Determine urgency level based on risk category and score
     *
     * @param string $category
     * @param int $score
     * @return string
     */
    private static function determineUrgencyFromCategory(string $category, int $score): string
    {
        $urgency = 'MONITORING'; // Default
        
        switch ($category) {
            case 'KRITIS':
                $urgency = 'DARURAT';
                break;
            case 'TINGGI':
                $urgency = 'SEGERA';
                break;
            case 'SEDANG':
                $urgency = 'PERHATIAN';
                break;
            case 'RENDAH':
            default:
                $urgency = 'MONITORING';
                break;
        }
        
        return $urgency;
    }
}
