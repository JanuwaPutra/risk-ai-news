<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tokoh extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nama',
        'alias',
        'jenis_kelamin',
        'kta',
        'jabatan',
        'tingkat',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tokoh';
} 