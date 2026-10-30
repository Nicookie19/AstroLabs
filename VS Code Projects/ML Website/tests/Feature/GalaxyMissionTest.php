<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\Explorer;
use App\Models\GalaxyImage;
use App\Models\MissionRun;
use App\Models\MlModel;
use App\Services\ML\NeuralNetworkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalaxyMissionTest extends TestCase
{
    use RefreshDatabase;

    private function preparedDataset(): array
    {
        $dataset = Dataset::factory()->create([
            'mission_key' => 'galaxy',
            'key' => 'galaxy10_sdss',
            'label_names' => ['Elliptical', 'Spiral', 'Edge-on disk'],
            'is_default' => false,
        ]);
        $image = GalaxyImage::factory()->create([
            'dataset_id' => $dataset->id,
            'split' => 'test',
            'label' => 'Spiral',
            'pixels' => array_fill(0, 256, 0.5),
        ]);
        $model = MlModel::factory()->create([
            'dataset_id' => $dataset->id,
            'name' => 'neural_network_galaxy_galaxy10_sdss',
        ]);

        return [$dataset, $image, $model];
    }

    private function mockPrediction(MlModel $expectedModel): void
    {
        $this->mock(NeuralNetworkService::class, function ($mock) use ($expectedModel) {
            $mock->shouldReceive('predict')->once()
                ->withArgs(fn ($pixels, $model) => count($pixels) === 256 && $model->id === $expectedModel->id)
                ->andReturn([
                    'prediction' => 'Spiral',
                    'probabilities' => ['Elliptical' => 10, 'Spiral' => 80, 'Edge-on disk' => 10],
                    'explanation' => [],
                ]);
        });
    }

    public function test_gallery_defaults_to_available_sdss_when_decals_has_no_images(): void
    {
        Dataset::factory()->create([
            'mission_key' => 'galaxy', 'key' => 'galaxy10_deca', 'is_default' => true,
        ]);
        [, $image] = $this->preparedDataset();

        $this->getJson('/api/missions/galaxy/gallery')
            ->assertOk()
            ->assertJsonPath('dataset.key', 'galaxy10_sdss')
            ->assertJsonPath('images.0.id', $image->id);
    }

    public function test_classify_accepts_gallery_image_with_null_pixels_and_json_dataset(): void
    {
        [$dataset, $image, $model] = $this->preparedDataset();
        $this->mockPrediction($model);
        $explorer = Explorer::factory()->create();

        $response = $this->withHeader('X-Session-Token', $explorer->session_token)
            ->postJson('/api/missions/galaxy/classify', [
                'dataset' => 'galaxy10_sdss',
                'image_id' => $image->id,
                'pixels' => null,
                'user_guess_label' => 'Spiral',
            ])
            ->assertOk()
            ->assertJsonPath('classification.prediction', 'Spiral');

        $this->assertDatabaseHas('mission_runs', [
            'id' => $response->json('run_id'),
            'dataset_id' => $dataset->id,
            'explorer_id' => $explorer->id,
            'points' => 0,
        ]);
    }

    public function test_empty_or_malformed_pixel_inputs_return_validation_errors(): void
    {
        $this->postJson('/api/missions/galaxy/classify', [
            'image_id' => null, 'pixels' => null, 'user_guess_label' => 'Spiral',
        ])->assertUnprocessable()->assertJsonValidationErrors(['image_id', 'pixels']);

        $this->postJson('/api/missions/galaxy/classify', [
            'pixels' => 'invalid', 'user_guess_label' => 'Spiral',
        ])->assertUnprocessable()->assertJsonValidationErrors('pixels');
    }

    public function test_reveal_scores_the_saved_guess_once_and_ignores_browser_guesses(): void
    {
        [$dataset, $image, $model] = $this->preparedDataset();
        $this->mockPrediction($model);
        $explorer = Explorer::factory()->create(['total_points' => 0]);
        $this->withHeader('X-Session-Token', $explorer->session_token);
        $runId = $this->postJson('/api/missions/galaxy/classify', [
            'dataset' => $dataset->key,
            'image_id' => $image->id,
            'pixels' => null,
            'user_guess_label' => 'Elliptical',
        ])->assertOk()->json('run_id');

        $payload = [
            'dataset' => $dataset->key,
            'image_id' => $image->id,
            'run_id' => $runId,
            'user_guess_label' => 'Spiral',
            'model_guess_label' => 'Elliptical',
        ];
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/missions/galaxy/reveal', $payload)
                ->assertOk()
                ->assertJsonPath('user_guess', 'Elliptical')
                ->assertJsonPath('model_guess', 'Spiral')
                ->assertJsonPath('points', 25);
        }

        $this->assertSame(25, $explorer->fresh()->total_points);
        $this->assertSame(25, MissionRun::findOrFail($runId)->points);
    }

    public function test_reveal_requires_a_round_owned_by_the_explorer_for_this_image(): void
    {
        [$dataset, $image] = $this->preparedDataset();
        $owner = Explorer::factory()->create();
        $other = Explorer::factory()->create(['total_points' => 0]);
        $run = MissionRun::create([
            'explorer_id' => $owner->id, 'mission_key' => 'galaxy', 'dataset_id' => $dataset->id,
            'inputs' => ['image_id' => $image->id], 'output' => ['prediction' => 'Spiral'],
            'user_guess' => ['label' => 'Spiral'], 'points' => 0,
        ]);

        $this->withHeader('X-Session-Token', $other->session_token)
            ->postJson('/api/missions/galaxy/reveal', [
                'dataset' => $dataset->key, 'image_id' => $image->id, 'run_id' => $run->id,
            ])->assertConflict();
        $this->assertSame(0, $other->fresh()->total_points);
        $this->assertSame(0, $run->fresh()->points);
    }
}
