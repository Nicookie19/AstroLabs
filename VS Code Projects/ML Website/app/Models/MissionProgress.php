<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_token', 'mission_key', 'completed_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}