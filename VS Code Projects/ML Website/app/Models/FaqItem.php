<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'question', 'answer', 'sort_order'
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}