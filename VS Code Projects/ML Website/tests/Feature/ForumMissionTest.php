<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Dataset;
use App\Models\ForumQuestion;
use App\Models\MlModel;
use App\Models\Explorer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ForumMissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CompanyContentSeeder::class);
    }

    public function test_forum_dataset_endpoint_returns_paginated_data()
    {
        $dataset = Dataset::factory()->create([
            'mission_key' => 'forum',
            'key' => 'test_dataset',
            'is_default' => true,
        ]);

        ForumQuestion::factory()->count(5)->create([
            'dataset_id' => $dataset->id,
            'category' => 'Planetary Science',
        ]);

        $response = $this->getJson("/api/missions/forum/dataset?dataset=test_dataset");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'dataset',
                'questions' => ['data', 'links', 'first_page_url', 'last_page_url', 'next_page_url', 'prev_page_url', 'current_page', 'last_page', 'per_page', 'total'],
                'category_counts',
                'attribution',
            ]);
    }

    public function test_forum_classify_requires_question()
    {
        $dataset = Dataset::factory()->create([
            'mission_key' => 'forum',
            'key' => 'test_dataset',
            'is_default' => true,
        ]);

        MlModel::factory()->create([
            'dataset_id' => $dataset->id,
            'name' => 'naive_bayes_forum_test_dataset',
            'parameters' => [
                'vocabulary' => ['mars', 'rover'],
                'class_priors' => ['Planetary Science' => 0.33],
                'word_probs' => ['Planetary Science' => ['mars' => 0.1, 'rover' => 0.1]],
                'categories' => ['Planetary Science', 'Stellar Physics', 'Space Exploration Tech'],
            ],
            'metrics' => ['test_accuracy' => 0.8],
        ]);

        $response = $this->postJson("/api/missions/forum/classify", [
            'dataset' => 'test_dataset',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['question']);
    }

    public function test_forum_classify_returns_classification_with_explanation()
    {
        $dataset = Dataset::factory()->create([
            'mission_key' => 'forum',
            'key' => 'test_dataset',
            'is_default' => true,
        ]);

        MlModel::factory()->create([
            'dataset_id' => $dataset->id,
            'name' => 'naive_bayes_forum_test_dataset',
            'parameters' => [
                'vocabulary' => ['mars', 'rover', 'water'],
                'class_priors' => [
                    'Planetary Science' => 0.33,
                    'Stellar Physics' => 0.33,
                    'Space Exploration Tech' => 0.34,
                ],
                'word_probs' => [
                    'Planetary Science' => ['mars' => 0.1, 'rover' => 0.1, 'water' => 0.05],
                    'Stellar Physics' => ['mars' => 0.01, 'rover' => 0.01, 'water' => 0.01],
                    'Space Exploration Tech' => ['mars' => 0.01, 'rover' => 0.01, 'water' => 0.01],
                ],
                'categories' => ['Planetary Science', 'Stellar Physics', 'Space Exploration Tech'],
            ],
            'metrics' => ['test_accuracy' => 0.8],
        ]);

        $explorer = Explorer::factory()->create();

        $response = $this->withHeaders(['X-Session-Token' => $explorer->session_token])
            ->postJson("/api/missions/forum/classify?dataset=test_dataset", [
                'question' => 'Mars rover finds water on red planet',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'classification' => ['category', 'confidence', 'all_scores', 'explanation'],
                'points',
            ]);
    }
}