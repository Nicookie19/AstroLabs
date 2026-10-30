<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForumQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_site', 'title', 'tags', 'category', 'url', 'score', 'dataset_id'
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function dataset()
    {
        return $this->belongsTo(Dataset::class);
    }
}