<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoiCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'kepoi_name', 'disposition', 'orbital_period_days', 'transit_depth_ppm',
        'transit_duration_hours', 'signal_to_noise', 'planet_radius_earths', 'dataset_id'
    ];

    protected $casts = [
        'orbital_period_days' => 'decimal:4',
        'transit_depth_ppm' => 'decimal:4',
        'transit_duration_hours' => 'decimal:4',
        'signal_to_noise' => 'decimal:4',
        'planet_radius_earths' => 'decimal:4',
    ];

    public function dataset()
    {
        return $this->belongsTo(Dataset::class);
    }
}