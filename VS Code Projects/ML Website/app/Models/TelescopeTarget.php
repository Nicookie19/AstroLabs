<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelescopeTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'catalog_id', 'category', 'distance_rank',
        'apparent_magnitude', 'constellation', 'dataset_id'
    ];

    protected $casts = [
        'distance_rank' => 'integer',
        'apparent_magnitude' => 'decimal:2',
    ];

    public function dataset()
    {
        return $this->belongsTo(Dataset::class);
    }
}