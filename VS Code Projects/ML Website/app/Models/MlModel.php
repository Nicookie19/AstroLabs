<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MlModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'parameters', 'metrics', 'dataset_id', 'trained_at'
    ];

    protected $casts = [
        'parameters' => 'array',
        'metrics' => 'array',
        'trained_at' => 'datetime',
    ];

    public function dataset()
    {
        return $this->belongsTo(Dataset::class);
    }
}