<?php

namespace Database\Factories;

use App\Models\ForumQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

class ForumQuestionFactory extends Factory
{
    protected $model = ForumQuestion::class;

    public function definition(): array
    {
        $categories = ['Planetary Science', 'Stellar Physics', 'Space Exploration Tech'];
        $sites = ['astronomy', 'space'];
        
        return [
            'source_site' => $this->faker->randomElement($sites),
            'title' => $this->faker->sentence(6),
            'tags' => json_encode($this->faker->randomElements(
                ['planet', 'star', 'rocket', 'mars', 'black-hole', 'exoplanet', 'telescope', 'nasa', 'iss'],
                $this->faker->numberBetween(1, 5)
            )),
            'category' => $this->faker->randomElement($categories),
            'url' => $this->faker->url(),
            'score' => $this->faker->numberBetween(0, 500),
            'dataset_id' => null,
        ];
    }

    public function planetaryScience(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Planetary Science',
            'source_site' => 'astronomy',
            'tags' => json_encode(['planet', 'mars', 'exoplanet']),
        ]);
    }

    public function stellarPhysics(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Stellar Physics',
            'source_site' => 'astronomy',
            'tags' => json_encode(['star', 'supernova', 'black-hole']),
        ]);
    }

    public function spaceExploration(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Space Exploration Tech',
            'source_site' => 'space',
            'tags' => json_encode(['rocket', 'iss', 'nasa']),
        ]);
    }
}