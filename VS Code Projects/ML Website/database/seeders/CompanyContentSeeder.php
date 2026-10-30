<?php

namespace Database\Seeders;

use App\Models\TeamMember;
use App\Models\CompanyService;
use App\Models\FaqItem;
use App\Models\RoadmapItem;
use Illuminate\Database\Seeder;

class CompanyContentSeeder extends Seeder
{
    public function run(): void
    {
        TeamMember::truncate();
        CompanyService::truncate();
        FaqItem::truncate();
        RoadmapItem::truncate();

        TeamMember::insert([
            [
                'name' => 'Nico Mahipus',
                'role' => 'Lead Developer',
                'bio' => 'Leads development of Astrolabs.',
                'avatar_initials' => 'NM',
                'sort_order' => 1,
            ],
            [
                'name' => 'Kurt Vincent Lopena',
                'role' => 'UI/UX Developer',
                'bio' => 'Designs the user interface and experience.',
                'avatar_initials' => 'KL',
                'sort_order' => 2,
            ],
            [
                'name' => 'James Reino Allawan',
                'role' => 'QA and Documentation',
                'bio' => 'Supports quality assurance and project documentation.',
                'avatar_initials' => 'JA',
                'sort_order' => 3,
            ],
            [
                'name' => 'Ghynne Mitch Eucare',
                'role' => 'QA and Documentation',
                'bio' => 'Supports quality assurance and project documentation.',
                'avatar_initials' => 'GE',
                'sort_order' => 4,
            ],
        ]);

        CompanyService::insert([
            [
                'name' => 'Orbit Lab',
                'description' => 'Interactive orbital mechanics simulator using real exoplanet data. Predict periods, explore Kepler\'s laws, and compare planetary systems.',
                'algorithm_key' => 'linear_regression',
                'icon' => 'orbit',
                'sort_order' => 1,
            ],
            [
                'name' => 'Telescope Academy',
                'description' => 'Learn optimal telescope settings for any target. Expert decision tree guides filter, exposure, and aperture choices.',
                'algorithm_key' => 'decision_tree',
                'icon' => 'telescope',
                'sort_order' => 2,
            ],
            [
                'name' => 'Exoplanet Atlas',
                'description' => 'Find exoplanet twins by physical properties. KNN search through thousands of confirmed planets with real similarity metrics.',
                'algorithm_key' => 'knn',
                'icon' => 'planet',
                'sort_order' => 3,
            ],
            [
                'name' => 'Transit Observatory',
                'description' => 'Detect exoplanet transits in noisy light curves. SVM classifier with adjustable margin for Kepler and TESS data.',
                'algorithm_key' => 'svm',
                'icon' => 'transit',
                'sort_order' => 4,
            ],
            [
                'name' => 'Galaxy Zoo ML',
                'description' => 'Classify galaxy morphology with a neural network. Real DESI Legacy Imaging Surveys images, trained from scratch.',
                'algorithm_key' => 'neural_network',
                'icon' => 'galaxy',
                'sort_order' => 5,
            ],
            [
                'name' => 'Forum Intelligence',
                'description' => 'Automatically categorize space science questions. Naive Bayes on Stack Exchange titles with full explanation.',
                'algorithm_key' => 'naive_bayes',
                'icon' => 'forum',
                'sort_order' => 6,
            ],
        ]);

        FaqItem::insert([
            [
                'question' => 'Is Astrolabs a real company?',
                'answer' => 'No, Astrolabs is a fictional company created for the ELEC026.23 Machine Learning final exam project. All company content is invented for educational purposes.',
                'sort_order' => 1,
            ],
            [
                'question' => 'Are the astronomical datasets real?',
                'answer' => 'Yes! All astronomical data comes from real sources: NASA Exoplanet Archive, Kepler/TESS mission data, Galaxy10 DECaLS/SDSS surveys, OpenNGC catalog, and Stack Exchange. We never generate fake astronomical data.',
                'sort_order' => 2,
            ],
            [
                'question' => 'Why do the ML models run in PHP instead of Python?',
                'answer' => 'This is an educational requirement: the algorithms must be implemented in plain PHP so students can read, understand, and explain every line of code. No black-box libraries at runtime.',
                'sort_order' => 3,
            ],
            [
                'question' => 'Can I use my own data with these models?',
                'answer' => 'The models are trained on specific datasets. For the Galaxy Classifier, you can upload any image which will be converted to 16×16 grayscale. For other missions, inputs must match the feature schema of the selected dataset.',
                'sort_order' => 4,
            ],
            [
                'question' => 'How accurate are the models?',
                'answer' => 'Each mission page shows held-out test metrics honestly. The Orbit Calculator achieves R² ~0.99 on Solar System data but lower on exoplanets. The Galaxy Classifier reaches ~60-70% on 16×16 images. We show confusion matrices and explain limitations.',
                'sort_order' => 5,
            ],
            [
                'question' => 'What is the "Explorer" onboarding?',
                'answer' => 'On first visit, you create an explorer profile (callsign + experience level). This unlocks missions, tracks your progress, saves your runs, and adjusts explanation depth: Beginner (analogies), Intermediate (formulas), Advanced (full math).',
                'sort_order' => 6,
            ],
            [
                'question' => 'Can I compare results across different datasets?',
                'answer' => 'Yes! The Orbit Calculator has a "Compare Datasets" feature showing predictions and learned exponents side-by-side for all 5 datasets. Other missions let you switch datasets via the dropdown, which reloads the model and inputs.',
                'sort_order' => 7,
            ],
            [
                'question' => 'Where can I find the source code for the ML algorithms?',
                'answer' => 'All algorithms are in app/Services/ML/ as plain PHP classes: LinearRegressionService, NaiveBayesService, KnnService, SvmService, DecisionTreeService, NeuralNetworkService. No external dependencies.',
                'sort_order' => 8,
            ],
        ]);

        RoadmapItem::insert([
            [
                'title' => 'Add radial velocity curve fitting mission',
                'description' => 'New mission using Gaussian Process regression to model stellar radial velocity curves.',
                'status' => 'planned',
                'target_date' => '2025-06-30',
                'sort_order' => 1,
            ],
            [
                'title' => 'Implement Bayesian optimization for hyperparameters',
                'description' => 'Add automated hyperparameter tuning for all models with acquisition function visualization.',
                'status' => 'in_progress',
                'target_date' => '2025-03-31',
                'sort_order' => 2,
            ],
            [
                'title' => 'Multi-wavelength galaxy classification',
                'description' => 'Extend Galaxy Classifier to use ugriz bands from SDSS/DECaLS instead of single-band grayscale.',
                'status' => 'planned',
                'target_date' => '2025-09-30',
                'sort_order' => 3,
            ],
            [
                'title' => 'Real-time transit detection simulator',
                'description' => 'Stream simulated light curves and detect transits in real-time with sliding window SVM.',
                'status' => 'planned',
                'target_date' => '2025-12-31',
                'sort_order' => 4,
            ],
            [
                'title' => 'Deploy to production with CI/CD',
                'description' => 'GitHub Actions pipeline for testing, building, and deploying to Laravel Forge.',
                'status' => 'completed',
                'target_date' => '2024-12-31',
                'sort_order' => 5,
            ],
        ]);

        $this->command->info('Company content seeded successfully.');
    }
}
