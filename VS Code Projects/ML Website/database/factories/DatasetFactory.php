<?php

namespace Database\Factories;

use App\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\Factory;

class DatasetFactory extends Factory
{
    protected $model = Dataset::class;

    public function definition(): array
    {
        return [
            'mission_key' => $this->faker->randomElement(['orbit', 'forum', 'exoplanets', 'transit', 'telescope', 'galaxy']),
            'key' => $this->faker->unique()->slug(2),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'publisher' => $this->faker->company(),
            'url' => $this->faker->url(),
            'license' => 'CC BY 4.0',
            'row_count' => $this->faker->numberBetween(100, 10000),
            'retrieved_at' => now(),
            'citation_text' => $this->faker->paragraph(),
            'feature_schema' => json_encode(['feature1' => ['unit' => 'unit', 'min' => 0, 'max' => 100, 'step' => 1]]),
            'label_names' => json_encode(['Label 1', 'Label 2']),
            'is_default' => false,
        ];
    }

    public function orbit(): static
    {
        return $this->state(fn (array $attributes) => [
            'mission_key' => 'orbit',
            'key' => 'test_orbit_' . $this->faker->unique()->numberBetween(1, 1000),
            'feature_schema' => json_encode([
                'semi_major_axis_au' => ['unit' => 'AU', 'min' => 0.01, 'max' => 100, 'step' => 0.01],
                'star_mass_solar' => ['unit' => 'Solar masses', 'min' => 0.08, 'max' => 10, 'step' => 0.01],
            ]),
            'label_names' => null,
        ]);
    }

    public function forum(): static
    {
        return $this->state(fn (array $attributes) => [
            'mission_key' => 'forum',
            'key' => 'test_forum_' . $this->faker->unique()->numberBetween(1, 1000),
            'feature_schema' => json_encode([
                'question_text' => ['unit' => 'text', 'min' => 10, 'max' => 500, 'step' => 1],
            ]),
            'label_names' => json_encode(['Planetary Science', 'Stellar Physics', 'Space Exploration Tech']),
        ]);
    }
}