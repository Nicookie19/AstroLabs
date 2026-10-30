<?php

namespace App\Services\ML;

use App\Models\ForumQuestion;
use App\Models\MlModel;
use App\Models\Dataset;
use Illuminate\Support\Facades\Log;

class NaiveBayesService
{
    private array $stopWords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from',
        'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
        'will', 'would', 'could', 'should', 'may', 'might', 'can', 'must', 'shall',
        'what', 'when', 'where', 'who', 'why', 'how', 'which', 'that', 'this', 'these', 'those',
        'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
        'my', 'your', 'his', 'her', 'its', 'our', 'their', 'mine', 'yours', 'hers', 'ours', 'theirs',
        'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'about', 'above', 'after', 'again', 'against', 'all', 'as', 'before', 'below', 'between',
        'both', 'during', 'each', 'few', 'further', 'here', 'more', 'most', 'other', 'out', 'over',
        'own', 'same', 'so', 'some', 'such', 'than', 'then', 'there', 'these', 'they', 'this',
        'those', 'through', 'under', 'until', 'up', 'very', 'what', 'when', 'where', 'which',
        'while', 'who', 'whom', 'why', 'will', 'with', 'you', 'your', 'yours', 'yourself',
        'yourselves', 'space', 'planet', 'star', 'astronomy', 'question', 'answer', 'ask', 'need',
    ];

    public function train(Dataset $dataset): MlModel
    {
        $questions = ForumQuestion::where('dataset_id', $dataset->id)
            ->get(['title', 'category'])
            ->toArray();

        if (count($questions) < 30) {
            throw new \Exception('Insufficient data for training. Need at least 30 samples.');
        }

        $n = count($questions);
        $split = (int)($n * 0.8);
        
        shuffle($questions);
        
        $trainData = array_slice($questions, 0, $split);
        $testData = array_slice($questions, $split);

        $model = $this->fit($trainData);
        
        $trainMetrics = $this->evaluate($trainData, $model);
        $testMetrics = $this->evaluate($testData, $model);

        $parameters = [
            'vocabulary' => $model['vocabulary'],
            'class_priors' => $model['class_priors'],
            'word_probs' => $model['word_probs'],
            'categories' => $model['categories'],
        ];

        $metrics = [
            'train_accuracy' => $trainMetrics['accuracy'],
            'test_accuracy' => $testMetrics['accuracy'],
            'train_confusion_matrix' => $trainMetrics['confusion_matrix'],
            'test_confusion_matrix' => $testMetrics['confusion_matrix'],
            'train_samples' => count($trainData),
            'test_samples' => count($testData),
        ];

        return MlModel::create([
            'name' => 'naive_bayes_forum_' . $dataset->key,
            'parameters' => $parameters,
            'metrics' => $metrics,
            'dataset_id' => $dataset->id,
            'trained_at' => now(),
        ]);
    }

    private function fit(array $data): array
    {
        $categories = ['Planetary Science', 'Stellar Physics', 'Space Exploration Tech'];
        $vocabulary = [];
        $classCounts = array_fill_keys($categories, 0);
        $wordCounts = [];

        foreach ($categories as $cat) {
            $wordCounts[$cat] = [];
        }

        foreach ($data as $row) {
            $cat = $row['category'];
            $classCounts[$cat]++;
            $tokens = $this->tokenize($row['title']);
            
            foreach ($tokens as $token) {
                $vocabulary[$token] = true;
                $wordCounts[$cat][$token] = ($wordCounts[$cat][$token] ?? 0) + 1;
            }
        }

        $vocabList = array_keys($vocabulary);
        $vocabSize = count($vocabList);

        $classPriors = [];
        foreach ($categories as $cat) {
            $classPriors[$cat] = $classCounts[$cat] / count($data);
        }

        $wordProbs = [];
        foreach ($categories as $cat) {
            $totalWordsInClass = array_sum($wordCounts[$cat]);
            $wordProbs[$cat] = [];
            foreach ($vocabList as $word) {
                $wordProbs[$cat][$word] = ($wordCounts[$cat][$word] ?? 0) + 1;
                $wordProbs[$cat][$word] /= ($totalWordsInClass + $vocabSize);
            }
        }

        return [
            'vocabulary' => $vocabList,
            'class_priors' => $classPriors,
            'word_probs' => $wordProbs,
            'categories' => $categories,
        ];
    }

    private function tokenize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $tokens = array_filter(explode(' ', $text), function($t) {
            return strlen($t) > 1 && !in_array($t, $this->stopWords);
        });
        return array_values($tokens);
    }

    private function evaluate(array $data, array $model): array
    {
        $categories = $model['categories'];
        $confusionMatrix = [];
        foreach ($categories as $cat) {
            $confusionMatrix[$cat] = array_fill_keys($categories, 0);
        }

        $correct = 0;
        foreach ($data as $row) {
            $prediction = $this->predictCategory($row['title'], $model);
            $actual = $row['category'];
            $confusionMatrix[$actual][$prediction]++;
            if ($prediction === $actual) $correct++;
        }

        return [
            'accuracy' => count($data) > 0 ? $correct / count($data) : 0,
            'confusion_matrix' => $confusionMatrix,
        ];
    }

    public function predictCategory(string $title, array $model): string
    {
        $tokens = $this->tokenize($title);
        $categories = $model['categories'];
        $bestCategory = '';
        $bestScore = -INF;

        foreach ($categories as $cat) {
            $score = log($model['class_priors'][$cat]);
            foreach ($tokens as $token) {
                if (isset($model['word_probs'][$cat][$token])) {
                    $score += log($model['word_probs'][$cat][$token]);
                } else {
                    $vocabSize = count($model['vocabulary']);
                    $totalWordsInClass = array_sum(array_column($model['word_probs'][$cat], 0)) * $vocabSize;
                    $score += log(1 / ($totalWordsInClass + $vocabSize));
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCategory = $cat;
            }
        }

        return $bestCategory;
    }

    public function classify(string $title, MlModel $model): array
    {
        $parameters = $model->parameters;
        $categories = $parameters['categories'];
        $tokens = $this->tokenize($title);

        $scores = [];
        $wordContributions = [];

        foreach ($categories as $cat) {
            $score = log($parameters['class_priors'][$cat]);
            $contributions = [];

            foreach ($tokens as $token) {
                if (isset($parameters['word_probs'][$cat][$token])) {
                    $prob = $parameters['word_probs'][$cat][$token];
                    $logProb = log($prob);
                    $score += $logProb;
                    $contributions[] = [
                        'word' => $token,
                        'probability' => $prob,
                        'log_probability' => $logProb,
                    ];
                }
            }

            usort($contributions, fn($a, $b) => $b['log_probability'] <=> $a['log_probability']);
            $wordContributions[$cat] = array_slice($contributions, 0, 5);
            $scores[$cat] = $score;
        }

        arsort($scores);
        $winningCategory = array_key_first($scores);

        $explanation = [
            'priors' => $parameters['class_priors'],
            'winning_category' => $winningCategory,
            'log_posteriors' => $scores,
            'top_words_for_winner' => $wordContributions[$winningCategory] ?? [],
            'top_words_for_others' => [],
        ];

        foreach ($categories as $cat) {
            if ($cat !== $winningCategory) {
                $explanation['top_words_for_others'][$cat] = $wordContributions[$cat] ?? [];
            }
        }

        return [
            'category' => $winningCategory,
            'confidence' => $this->softmax(array_values($scores))[array_search($winningCategory, array_keys($scores))],
            'all_scores' => $scores,
            'explanation' => $explanation,
        ];
    }

    private function softmax(array $values): array
    {
        $maxVal = max($values);
        $expValues = array_map(fn($v) => exp($v - $maxVal), $values);
        $sum = array_sum($expValues);
        return array_map(fn($v) => $v / $sum, $expValues);
    }

    public function getDatasetStats(Dataset $dataset): array
    {
        return ForumQuestion::where('dataset_id', $dataset->id)
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();
    }
}