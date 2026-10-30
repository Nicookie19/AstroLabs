<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'explorer_id', 'mission_key', 'dataset_id', 'inputs', 'output', 'user_guess', 'points'
    ];

    protected $casts = [
        'inputs' => 'array',
        'output' => 'array',
        'user_guess' => 'array',
        'points' => 'integer',
    ];

    public function explorer()
    {
        return $this->belongsTo(Explorer::class);
    }

    public function dataset()
    {
        return $this->belongsTo(Dataset::class);
    }
}