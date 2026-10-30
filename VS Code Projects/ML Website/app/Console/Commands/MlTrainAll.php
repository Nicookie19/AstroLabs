<?php

namespace App\Console\Commands;

use App\Models\Dataset;
use App\Models\MlModel;
use App\Services\ML\LinearRegressionService;
use App\Services\ML\NaiveBayesService;
use App\Services\ML\KnnService;
use App\Services\ML\SvmService;
use App\Services\ML\DecisionTreeService;
use App\Services\ML\NeuralNetworkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MlTrainAll extends Command
{
    protected $signature = 'ml:train-all {--mission= : Train only specific mission (orbit, forum, exoplanets, transit, telescope, galaxy)}';
    protected $description = 'Train all ML models for all datasets';

    public function handle(): int
    {
        $mission = $this->option('mission');
        
        $this->info('Training all ML models...');
        Log::info('Starting ML training for all models');

        $datasets = Dataset::when($mission, fn($q) => $q->where('mission_key', $mission))->get();

        if ($datasets->isEmpty()) {
            $this->warn('No datasets found to train.');
            return Command::SUCCESS;
        }

        $services = [
            'orbit' => [LinearRegressionService::class, 'train'],
            'forum' => [NaiveBayesService::class, 'train'],
            'exoplanets' => [KnnService::class, 'train'],
            'transit' => [SvmService::class, 'train'],
            'telescope' => [DecisionTreeService::class, 'train'],
            'galaxy' => [NeuralNetworkService::class, 'train'],
        ];

        foreach ($datasets as $dataset) {
            if (!isset($services[$dataset->mission_key])) {
                $this->warn("No service for mission: {$dataset->mission_key}");
                continue;
            }

            $existing = MlModel::where('dataset_id', $dataset->id)->first();
            if ($existing) {
                $this->info("  Skipping {$dataset->mission_key}/{$dataset->key} - model already exists. Use --force to retrain.");
                continue;
            }

            $this->info("Training {$dataset->mission_key} / {$dataset->key}...");
            
            try {
                [$serviceClass, $method] = $services[$dataset->mission_key];
                $service = app($serviceClass);
                $model = $service->$method($dataset);
                
                $this->info("  ✓ Trained: {$model->name}");
                $this->info("    Metrics: " . json_encode($model->metrics));
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                Log::error("Training failed for {$dataset->mission_key}/{$dataset->key}: {$e->getMessage()}");
            }
        }

        $this->info('All training complete.');
        return Command::SUCCESS;
    }
}
