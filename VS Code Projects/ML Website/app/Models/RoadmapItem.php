<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoadmapItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'status', 'target_date', 'sort_order'
    ];

    protected $casts = [
        'target_date' => 'date',
        'sort_order' => 'integer',
    ];
}