<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'algorithm_key', 'icon', 'sort_order'
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}