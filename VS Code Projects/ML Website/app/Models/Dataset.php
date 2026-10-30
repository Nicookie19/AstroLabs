<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dataset extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_key', 'key', 'title', 'description', 'publisher', 'url', 'license',
        'row_count', 'retrieved_at', 'citation_text', 'feature_schema', 'label_names', 'is_default'
    ];

    protected $casts = [
        'retrieved_at' => 'datetime',
        'row_count' => 'integer',
        'feature_schema' => 'array',
        'label_names' => 'array',
        'is_default' => 'boolean',
    ];

    public function getLabelNamesAttribute($value): array
    {
        // Legacy imports encoded JSON before Eloquent encoded it again.
        for ($i = 0; $i < 2 && is_string($value); $i++) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    public function exoplanets()
    {
        return $this->hasMany(Exoplanet::class);
    }

    public function koiCandidates()
    {
        return $this->hasMany(KoiCandidate::class);
    }

    public function forumQuestions()
    {
        return $this->hasMany(ForumQuestion::class);
    }

    public function telescopeTargets()
    {
        return $this->hasMany(TelescopeTarget::class);
    }

    public function galaxyImages()
    {
        return $this->hasMany(GalaxyImage::class);
    }

    public function mlModels()
    {
        return $this->hasMany(MlModel::class);
    }

    public function missionRuns()
    {
        return $this->hasMany(MissionRun::class);
    }
}
