<?php

namespace Database\Factories;

use App\Models\Explorer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExplorerFactory extends Factory
{
    protected $model = Explorer::class;

    public function definition(): array
    {
        return [
            'callsign' => $this->faker->unique()->userName(),
            'level' => $this->faker->randomElement(['Beginner', 'Intermediate', 'Advanced']),
            'session_token' => Str::uuid()->toString(),
            'total_points' => $this->faker->numberBetween(0, 500),
        ];
    }
}