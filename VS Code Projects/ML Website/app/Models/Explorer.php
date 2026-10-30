<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Explorer extends Model
{
    use HasFactory;

    protected $fillable = [
        'callsign', 'level', 'session_token', 'total_points'
    ];

    protected $casts = [
        'total_points' => 'integer',
    ];

    public function missionRuns()
    {
        return $this->hasMany(MissionRun::class);
    }
}