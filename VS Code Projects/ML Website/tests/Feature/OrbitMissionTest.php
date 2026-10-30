<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Dataset;
use App\Models\Exoplanet;
use App\Models\MlModel;
use App\Models\Explorer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrbitMissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CompanyContentSeeder::class);
    }

    public function test_orbit_dataset_endpoint_returns_paginated_data()
    {
        $dataset = Dataset::factory()->create([
            'mission_key' => 'orbit',
            'key' => 'test_dataset',
            'is_default' => true,
        ]);

        Exoplanet::factory()->count(5)->create([
            'dataset_id' => $dataset->id,
            'orbital_period_days' => 365.25,
            'semi_major_axis_au' => 1.0,
            'star_mass_solar' => 1.0,
        ]);

        $response = $this->getJson("/api/missions/orbit/dataset?dataset=test_dataset");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'dataset',
                'planets' => ['data', 'links', 'first_page_url', 'last_page_url', 'next_page_url', 'prev_page_url', 'current_page', 'last_page', 'per_page', 'total'],
                'honesty_note',
            ]);
    }

    public function test_orbit_predict_requires_input()
    {
        $dataset = Dataset::factory()->create([
            'mission_key' => 'orbit',
            'key' => 'test_dataset',
            'is_default' => true,
        ]);

        MlModel::factory()->create([
            'dataset_id' => $dataset->id,
            'name' => 'linear_regression_orbit_test_dataset',
            'parameters' => ['coefficients' => [0, 1.5, -0.5]],
            'metrics' => ['test_r2' => 0.99],
        ]);

        $response = $this->postJson("/api/missions/orbit/predict?dataset=test_dataset", [
            'dataset' => 'test_dataset',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['semi_major_axis_au', 'star_mass_solar']);
    }

    public function test_orbit_predict_returns_prediction_with_explanation()
    {
        $dataset = Dataset::factory()->create([
            'mission_key' => 'orbit',
            'key' => 'test_dataset',
            'is_default' => true,
        ]);

        MlModel::factory()->create([
            'dataset_id' => $dataset->id,
            'name' => 'linear_regression_orbit_test_dataset',
            'parameters' => ['coefficients' => [0.5, 1.5, -0.5]],
            'metrics' => ['test_r2' => 0.99],
        ]);

        $explorer = Explorer::factory()->create();

        $response = $this->withHeaders(['X-Session-Token' => $explorer->session_token])
            ->postJson("/api/missions/orbit/predict?dataset=test_dataset", [
                'semi_major_axis_au' => 1.0,
                'star_mass_solar' => 1.0,
            ]);

        if ($response->status() !== 200) {
            $this->fail("Response status: {$response->status()}, body: " . $response->getContent());
        }

        $response->assertStatus(200)
            ->assertJsonStructure([
                'prediction' => ['period_days', 'period_years', 'explanation'],
                'closest_real_planet',
                'comparison_data',
                'points',
            ]);
    }
}