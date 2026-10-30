<?php

namespace Database\Factories;

use App\Models\TelescopeTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

class TelescopeTargetFactory extends Factory
{
    protected $model = TelescopeTarget::class;

    public function definition(): array
    {
        $categories = ['Moon', 'Planet', 'Nebula', 'Cluster', 'Galaxy'];
        return [
            'name' => $this->faker->word() . ' ' . $this->faker->randomLetter(),
            'catalog_id' => $this->faker->randomElement(['M', 'NGC', 'IC']) . $this->faker->numberBetween(1, 7000),
            'category' => $this->faker->randomElement($categories),
            'distance_rank' => $this->faker->numberBetween(0, 3),
            'apparent_magnitude' => $this->faker->randomFloat(2, -10, 20),
            'constellation' => $this->faker->randomElement(['Orion', 'Andromeda', 'Ursa Major', 'Cassiopeia', 'Cygnus']),
            'dataset_id' => null,
        ];
    }
}