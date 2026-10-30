<?php

namespace Database\Factories;

use App\Models\KoiCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

class KoiCandidateFactory extends Factory
{
    protected $model = KoiCandidate::class;

    public function definition(): array
    {
        return [
            'kepoi_name' => 'K' . $this->faker->unique()->numberBetween(100000, 999999) . '.01',
            'disposition' => $this->faker->randomElement(['CONFIRMED', 'FALSE POSITIVE']),
            'orbital_period_days' => $this->faker->randomFloat(4, 0.5, 500),
            'transit_depth_ppm' => $this->faker->randomFloat(4, 10, 50000),
            'transit_duration_hours' => $this->faker->randomFloat(4, 0.5, 24),
            'signal_to_noise' => $this->faker->randomFloat(4, 5, 500),
            'planet_radius_earths' => $this->faker->randomFloat(4, 0.1, 30),
            'dataset_id' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'disposition' => 'CONFIRMED',
            'transit_depth_ppm' => $this->faker->randomFloat(4, 100, 50000),
            'signal_to_noise' => $this->faker->randomFloat(4, 20, 500),
        ]);
    }

    public function falsePositive(): static
    {
        return $this->state(fn (array $attributes) => [
            'disposition' => 'FALSE POSITIVE',
            'transit_depth_ppm' => $this->faker->randomFloat(4, 10, 500),
            'signal_to_noise' => $this->faker->randomFloat(4, 5, 20),
        ]);
    }
}