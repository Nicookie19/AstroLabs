<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'title', 'publisher', 'url', 'license', 'retrieved_at', 'row_count', 'citation_text'
    ];

    protected $casts = [
        'retrieved_at' => 'datetime',
    ];
}