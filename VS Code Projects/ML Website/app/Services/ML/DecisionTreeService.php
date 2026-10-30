<?php

namespace App\Services\ML;

use App\Models\TelescopeTarget;
use App\Models\MlModel;
use App\Models\Dataset;
use Illuminate\Support\Facades\Log;

class DecisionTreeService
{
    public function train(Dataset $dataset): MlModel
    {
        $targets = TelescopeTarget::where('dataset_id', $dataset->id)
            ->get(['name', 'category', 'distance_rank', 'apparent_magnitude', 'constellation'])
            ->toArray();

        $parameters = [
            'tree_rules' => $this->getTreeRules(),
            'note' => 'Rules are expert astrophotography rules of thumb, NOT learned from data. The tree structure is hardcoded based on domain knowledge.',
        ];

        $metrics = [
            'train_samples' => count($targets),
            'note' => 'Decision tree is rule-based, not trained. Accuracy metrics not applicable.',
        ];

        return MlModel::create([
            'name' => 'decision_tree_telescope_' . $dataset->key,
            'parameters' => $parameters,
            'metrics' => $metrics,
            'dataset_id' => $dataset->id,
            'trained_at' => now(),
        ]);
    }

    private function getTreeRules(): array
    {
        return [
            'root' => 'Bortle Scale',
            'branches' => [
                [
                    'condition' => 'Bortle ≥ 6 (Light polluted)',
                    'sub_branches' => [
                        [
                            'condition' => 'Distance Rank ≥ 2 (Outside Solar System)',
                            'result' => ['filter' => 'Light-pollution filter', 'exposure' => '180 s', 'aperture' => '200 mm'],
                            'path' => ['Bortle ≥ 6', 'Distance ≥ 2'],
                        ],
                        [
                            'condition' => 'Distance Rank < 2 (Solar System)',
                            'result' => ['filter' => 'Colour planetary filter', 'exposure' => '1/60 s', 'aperture' => '100 mm'],
                            'path' => ['Bortle ≥ 6', 'Distance < 2'],
                        ],
                    ],
                ],
                [
                    'condition' => 'Bortle < 6 (Dark sky)',
                    'sub_branches' => [
                        [
                            'condition' => 'Distance Rank ≥ 3 (Beyond Galaxy)',
                            'result' => ['filter' => 'No filter', 'exposure' => '600 s', 'aperture' => '300 mm'],
                            'path' => ['Bortle < 6', 'Distance ≥ 3'],
                        ],
                        [
                            'condition' => 'Distance Rank = 2 (Inside Galaxy, Outside Solar System)',
                            'result' => ['filter' => 'Hydrogen-alpha filter', 'exposure' => '240 s', 'aperture' => '200 mm'],
                            'path' => ['Bortle < 6', 'Distance = 2'],
                        ],
                        [
                            'condition' => 'Distance Rank < 2 (Solar System)',
                            'result' => ['filter' => 'Neutral density filter', 'exposure' => '1/125 s', 'aperture' => '80 mm'],
                            'path' => ['Bortle < 6', 'Distance < 2'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function recommend(int $bortle, TelescopeTarget $target, ?array $userGuess = null): array
    {
        $path = [];
        
        if ($bortle >= 6) {
            $path[] = 'Bortle ≥ 6 (Light polluted sky)';
            if ($target->distance_rank >= 2) {
                $path[] = 'Distance Rank ≥ 2 (Outside Solar System)';
                $result = ['filter' => 'Light-pollution filter', 'exposure' => '180 s', 'aperture' => '200 mm'];
            } else {
                $path[] = 'Distance Rank < 2 (Solar System object)';
                $result = ['filter' => 'Colour planetary filter', 'exposure' => '1/60 s', 'aperture' => '100 mm'];
            }
        } else {
            $path[] = 'Bortle < 6 (Dark sky)';
            if ($target->distance_rank >= 2) {
                $path[] = 'Distance Rank ≥ 2 (Outside Solar System)';
                if ($target->distance_rank >= 3) {
                    $path[] = 'Distance Rank ≥ 3 (Beyond our galaxy)';
                    $result = ['filter' => 'No filter', 'exposure' => '600 s', 'aperture' => '300 mm'];
                } else {
                    $path[] = 'Distance Rank = 2 (Inside Milky Way)';
                    $result = ['filter' => 'Hydrogen-alpha filter', 'exposure' => '240 s', 'aperture' => '200 mm'];
                }
            } else {
                $path[] = 'Distance Rank < 2 (Solar System object)';
                $result = ['filter' => 'Neutral density filter', 'exposure' => '1/125 s', 'aperture' => '80 mm'];
            }
        }

        $score = 0;
        $comparison = null;
        if ($userGuess) {
            $comparison = [
                'filter' => ['guess' => $userGuess['filter'] ?? '', 'actual' => $result['filter'], 'match' => ($userGuess['filter'] ?? '') === $result['filter']],
                'exposure' => ['guess' => $userGuess['exposure'] ?? '', 'actual' => $result['exposure'], 'match' => ($userGuess['exposure'] ?? '') === $result['exposure']],
                'aperture' => ['guess' => $userGuess['aperture'] ?? '', 'actual' => $result['aperture'], 'match' => ($userGuess['aperture'] ?? '') === $result['aperture']],
            ];
            foreach ($comparison as $c) {
                if ($c['match']) $score++;
            }
        }

        $explanation = [
            'tree_structure' => $this->getTreeRules(),
            'path_taken' => $path,
            'target' => [
                'name' => $target->name,
                'category' => $target->category,
                'distance_rank' => $target->distance_rank,
                'apparent_magnitude' => $target->apparent_magnitude,
                'constellation' => $target->constellation,
            ],
            'bortle_scale' => $bortle,
            'honesty_note' => 'These rules are expert astrophotography rules of thumb written by the team, NOT learned from a dataset. No public dataset of "correct telescope settings" exists.',
            'why_splits' => [
                'Bortle scale' => 'Light pollution washes out faint deep-sky objects. In bright skies, you need filters to cut skyglow and shorter exposures to avoid saturation.',
                'Distance rank' => 'Determines object brightness and angular size. Solar system objects are bright and small (need short exposure, planetary filters). Deep-sky objects are faint and large (need long exposure, light-gathering aperture).',
            ],
            'comparison' => $comparison,
            'score' => $score,
        ];

        return [
            'filter' => $result['filter'],
            'exposure' => $result['exposure'],
            'aperture' => $result['aperture'],
            'path' => $path,
            'explanation' => $explanation,
        ];
    }

    public function getTargets(Dataset $dataset, ?string $search = null): array
    {
        $query = TelescopeTarget::where('dataset_id', $dataset->id);
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('catalog_id', 'like', "%{$search}%")
                  ->orWhere('constellation', 'like', "%{$search}%");
            });
        }
        
        return $query->orderBy('distance_rank')->orderBy('name')->get()->toArray();
    }
}