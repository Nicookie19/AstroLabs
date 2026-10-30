<?php

namespace Database\Factories;

use App\Models\Exoplanet;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExoplanetFactory extends Factory
{
    protected $model = Exoplanet::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word() . ' ' . $this->faker->randomLetter(),
            'host_star' => $this->faker->word() . ' ' . $this->faker->randomLetter(),
            'orbital_period_days' => $this->faker->randomFloat(4, 0.5, 5000),
            'semi_major_axis_au' => $this->faker->randomFloat(6, 0.01, 100),
            'star_mass_solar' => $this->faker->randomFloat(4, 0.08, 10),
            'radius_earths' => $this->faker->randomFloat(4, 0.1, 30),
            'mass_earths' => $this->faker->randomFloat(4, 0.1, 5000),
            'bulk_density_g_cm3' => $this->faker->randomFloat(4, 0.1, 20),
            'equilibrium_temp_k' => $this->faker->randomFloat(2, 50, 3000),
            'discovery_year' => $this->faker->numberBetween(1995, 2024),
            'discovery_method' => $this->faker->randomElement(['Transit', 'Radial Velocity', 'Direct Imaging', 'Microlensing']),
            'dataset_id' => null,
        ];
    }

    public function withOrbitData(): static
    {
        return $this->state(fn (array $attributes) => [
            'orbital_period_days' => $this->faker->randomFloat(4, 1, 5000),
            'semi_major_axis_au' => $this->faker->randomFloat(6, 0.01, 100),
            'star_mass_solar' => $this->faker->randomFloat(4, 0.1, 10),
        ]);
    }

    public function withKnnData(): static
    {
        return $this->state(fn (array $attributes) => [
            'radius_earths' => $this->faker->randomFloat(4, 0.1, 30),
            'bulk_density_g_cm3' => $this->faker->randomFloat(4, 0.1, 20),
            'equilibrium_temp_k' => $this->faker->randomFloat(2, 50, 3000),
            'mass_earths' => $this->faker->randomFloat(4, 0.1, 5000),
        ]);
    }
}