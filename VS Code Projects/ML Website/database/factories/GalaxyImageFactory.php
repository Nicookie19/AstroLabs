<?php

namespace Database\Factories;

use App\Models\GalaxyImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalaxyImageFactory extends Factory
{
    protected $model = GalaxyImage::class;

    public function definition(): array
    {
        $labels = ['Elliptical', 'Spiral', 'Irregular', 'Edge-on disk'];
        return [
            'source_id' => 'GAL-' . $this->faker->unique()->numberBetween(10000, 99999),
            'label' => $this->faker->randomElement($labels),
            'original_label' => $this->faker->randomElement($labels),
            'image_path' => 'galaxies/sample_' . $this->faker->numberBetween(1, 100) . '.jpg',
            'pixels' => json_encode(array_fill(0, 256, $this->faker->randomFloat(4, 0, 1))),
            'split' => $this->faker->randomElement(['train', 'test']),
            'dataset_id' => null,
        ];
    }
}