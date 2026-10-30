<?php

namespace Database\Factories;

use App\Models\MlModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class MlModelFactory extends Factory
{
    protected $model = MlModel::class;

    public function definition(): array
    {
        return [
            'name' => 'test_model_' . $this->faker->unique()->numberBetween(1, 1000),
            'parameters' => ['coefficients' => [0.5, 1.5, -0.5]],
            'metrics' => ['test_r2' => 0.95, 'test_accuracy' => 0.9],
            'dataset_id' => null,
            'trained_at' => now(),
        ];
    }

    public function linearRegression(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'linear_regression_orbit_test',
            'parameters' => ['coefficients' => [0.5, 1.5, -0.5], 'feature_names' => ['intercept', 'ln_semi_major_axis', 'ln_star_mass']],
            'metrics' => ['test_r2' => 0.99, 'test_rmse' => 10.5],
        ]);
    }

    public function naiveBayes(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'naive_bayes_forum_test',
            'parameters' => [
                'vocabulary' => ['mars', 'rover'],
                'class_priors' => ['Planetary Science' => 0.33],
                'word_probs' => ['Planetary Science' => ['mars' => 0.1]],
                'categories' => ['Planetary Science', 'Stellar Physics', 'Space Exploration Tech'],
            ],
            'metrics' => ['test_accuracy' => 0.85],
        ]);
    }
}