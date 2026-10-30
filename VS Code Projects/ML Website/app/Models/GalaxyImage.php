<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalaxyImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id', 'label', 'original_label', 'image_path', 'pixels', 'split', 'dataset_id'
    ];

    protected $casts = [
        'pixels' => 'array',
    ];

    public function dataset()
    {
        return $this->belongsTo(Dataset::class);
    }
}