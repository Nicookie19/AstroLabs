<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exoplanet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'host_star', 'orbital_period_days', 'semi_major_axis_au',
        'star_mass_solar', 'radius_earths', 'mass_earths', 'bulk_density_g_cm3',
        'equilibrium_temp_k', 'discovery_year', 'discovery_method', 'dataset_id'
    ];

    protected $casts = [
        'orbital_period_days' => 'decimal:4',
        'semi_major_axis_au' => 'decimal:6',
        'star_mass_solar' => 'decimal:4',
        'radius_earths' => 'decimal:4',
        'mass_earths' => 'decimal:4',
        'bulk_density_g_cm3' => 'decimal:4',
        'equilibrium_temp_k' => 'decimal:2',
    ];

    public function dataset()
    {
        return $this->belongsTo(Dataset::class);
    }
}