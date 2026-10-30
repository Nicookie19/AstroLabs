<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataSource;
use App\Models\Dataset;
use App\Models\Exoplanet;
use App\Models\KoiCandidate;
use App\Models\ForumQuestion;
use App\Models\TelescopeTarget;
use App\Models\GalaxyImage;

class DatasetController extends Controller
{
    public function index()
    {
        $datasets = Dataset::orderBy('mission_key')->orderBy('is_default', 'desc')->get();
        
        $grouped = $datasets->groupBy('mission_key')->map(function($items, $mission) {
            $missionTitles = [
                'orbit' => 'Orbit Calculator (Linear Regression)',
                'forum' => 'Forum Sorter (Naive Bayes)',
                'exoplanets' => 'Exoplanet Twin Finder (KNN)',
                'transit' => 'Transit Hunter (SVM)',
                'telescope' => 'Telescope Setup Wizard (Decision Tree)',
                'galaxy' => 'Galaxy Classifier (Neural Network)',
            ];
            
            return [
                'mission' => $mission,
                'mission_title' => $missionTitles[$mission] ?? $mission,
                'datasets' => $items->map(function($d) {
                    return [
                        'key' => $d->key,
                        'title' => $d->title,
                        'description' => $d->description,
                        'publisher' => $d->publisher,
                        'url' => $d->url,
                        'license' => $d->license,
                        'row_count' => $d->row_count,
                        'retrieved_at' => $d->retrieved_at,
                        'citation_text' => $d->citation_text,
                        'feature_schema' => $d->feature_schema,
                        'label_names' => $d->label_names,
                        'is_default' => $d->is_default,
                    ];
                }),
            ];
        })->values();

        $sources = DataSource::all()->map(function($s) {
            return [
                'key' => $s->key,
                'title' => $s->title,
                'publisher' => $s->publisher,
                'url' => $s->url,
                'license' => $s->license,
                'retrieved_at' => $s->retrieved_at,
                'row_count' => $s->row_count,
                'citation_text' => $s->citation_text,
            ];
        });

        return response()->json([
            'datasets_by_mission' => $grouped,
            'sources' => $sources,
        ]);
    }

    public function show(string $missionKey, string $datasetKey)
    {
        $dataset = Dataset::where('mission_key', $missionKey)->where('key', $datasetKey)->firstOrFail();
        
        $preview = $this->getPreview($dataset);

        return response()->json([
            'dataset' => [
                'key' => $dataset->key,
                'title' => $dataset->title,
                'description' => $dataset->description,
                'publisher' => $dataset->publisher,
                'url' => $dataset->url,
                'license' => $dataset->license,
                'row_count' => $dataset->row_count,
                'retrieved_at' => $dataset->retrieved_at,
                'citation_text' => $dataset->citation_text,
                'feature_schema' => $dataset->feature_schema,
                'label_names' => $dataset->label_names,
                'is_default' => $dataset->is_default,
            ],
            'preview' => $preview,
        ]);
    }

    private function getPreview(Dataset $dataset): array
    {
        $limit = 10;
        
        return match($dataset->mission_key) {
            'orbit' => Exoplanet::where('dataset_id', $dataset->id)
                ->limit($limit)
                ->get(['name', 'host_star', 'orbital_period_days', 'semi_major_axis_au', 'star_mass_solar', 'discovery_method'])
                ->toArray(),
            'forum' => ForumQuestion::where('dataset_id', $dataset->id)
                ->limit($limit)
                ->get(['title', 'category', 'source_site', 'score'])
                ->toArray(),
            'exoplanets' => Exoplanet::where('dataset_id', $dataset->id)
                ->limit($limit)
                ->get(['name', 'host_star', 'radius_earths', 'bulk_density_g_cm3', 'equilibrium_temp_k', 'mass_earths'])
                ->toArray(),
            'transit' => KoiCandidate::where('dataset_id', $dataset->id)
                ->limit($limit)
                ->get(['kepoi_name', 'disposition', 'transit_depth_ppm', 'signal_to_noise', 'orbital_period_days'])
                ->toArray(),
            'telescope' => TelescopeTarget::where('dataset_id', $dataset->id)
                ->limit($limit)
                ->get(['name', 'catalog_id', 'category', 'distance_rank', 'apparent_magnitude'])
                ->toArray(),
            'galaxy' => GalaxyImage::where('dataset_id', $dataset->id)
                ->limit($limit)
                ->get(['source_id', 'label', 'original_label', 'split'])
                ->toArray(),
            default => [],
        };
    }
}
