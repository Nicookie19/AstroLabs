<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ML\LinearRegressionService;
use App\Services\ML\NaiveBayesService;

class LinearRegressionServiceTest extends TestCase
{
    public function test_gaussian_elimination_solves_simple_system()
    {
        $service = new LinearRegressionService();
        
        // System:
        // 2x + y = 5
        // x + 2y = 4
        // Solution: x = 2, y = 1
        $A = [[2, 1], [1, 2]];
        $b = [5, 4];
        
        $result = $service->gaussianElimination($A, $b);
        
        $this->assertEqualsWithDelta(2.0, $result[0], 0.001);
        $this->assertEqualsWithDelta(1.0, $result[1], 0.001);
    }

    public function test_gaussian_elimination_solves_3x3_system()
    {
        $service = new LinearRegressionService();
        
        // System:
        // x + y + z = 6
        // 2x + 3y + z = 14
        // x + 2y + 3z = 14
        // Solution: x = 0, y = 4, z = 2
        $A = [[1, 1, 1], [2, 3, 1], [1, 2, 3]];
        $b = [6, 14, 14];
        
        $result = $service->gaussianElimination($A, $b);
        
        $this->assertEqualsWithDelta(0.0, $result[0], 0.001);
        $this->assertEqualsWithDelta(4.0, $result[1], 0.001);
        $this->assertEqualsWithDelta(2.0, $result[2], 0.001);
    }

    public function test_fit_normal_equations_on_synthetic_data()
    {
        $service = new LinearRegressionService();
        
        // Generate data following ln(T) = 0.5 + 1.5*ln(a) - 0.5*ln(M)
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $ln_a = log(0.5 + $i * 0.05); // a from 0.5 to 5.5
            $ln_m = log(0.5 + $i * 0.02); // M from 0.5 to 2.5
            $ln_t = 0.5 + 1.5 * $ln_a - 0.5 * $ln_m; // no noise for exact test
            
            $data[] = [
                'orbital_period_days' => exp($ln_t),
                'semi_major_axis_au' => exp($ln_a),
                'star_mass_solar' => exp($ln_m),
            ];
        }
        
        $coeffs = $service->fitNormalEquations($data);
        
        // Should be close to [0.5, 1.5, -0.5]
        $this->assertEqualsWithDelta(0.5, $coeffs[0], 0.01);
        $this->assertEqualsWithDelta(1.5, $coeffs[1], 0.01);
        $this->assertEqualsWithDelta(-0.5, $coeffs[2], 0.01);
    }
}

class NaiveBayesServiceTest extends TestCase
{
    public function test_tokenize_removes_stopwords_and_punctuation()
    {
        $service = new NaiveBayesService();
        
        $text = "The Mars rover finds water on the red planet!";
        $tokens = $service->tokenize($text);
        
        $this->assertContains('mars', $tokens);
        $this->assertContains('rover', $tokens);
        $this->assertContains('finds', $tokens);
        $this->assertContains('water', $tokens);
        $this->assertContains('red', $tokens);
        $this->assertContains('planet', $tokens);
        $this->assertNotContains('the', $tokens);
        $this->assertNotContains('on', $tokens);
    }

    public function test_fit_and_predict_on_simple_data()
    {
        $service = new NaiveBayesService();
        
        $trainData = [
            ['title' => 'Mars rover discovers water', 'category' => 'Planetary Science'],
            ['title' => 'Mars atmosphere thin', 'category' => 'Planetary Science'],
            ['title' => 'Supernova explosion bright', 'category' => 'Stellar Physics'],
            ['title' => 'Black hole merges neutron star', 'category' => 'Stellar Physics'],
            ['title' => 'SpaceX launches rocket', 'category' => 'Space Exploration Tech'],
            ['title' => 'ISS astronaut spacewalk', 'category' => 'Space Exploration Tech'],
        ];
        
        $model = $service->fit($trainData);
        
        $this->assertArrayHasKey('vocabulary', $model);
        $this->assertArrayHasKey('class_priors', $model);
        $this->assertArrayHasKey('word_probs', $model);
        
        $prediction = $service->predictCategory('Mars rover finds ice', $model);
        $this->assertEquals('Planetary Science', $prediction);
    }

    public function test_evaluate_returns_accuracy_and_confusion_matrix()
    {
        $service = new NaiveBayesService();
        
        $trainData = [
            ['title' => 'Mars planet', 'category' => 'Planetary Science'],
            ['title' => 'Venus planet', 'category' => 'Planetary Science'],
            ['title' => 'Star supernova', 'category' => 'Stellar Physics'],
            ['title' => 'Sun solar', 'category' => 'Stellar Physics'],
            ['title' => 'Rocket launch', 'category' => 'Space Exploration Tech'],
            ['title' => 'Satellite orbit', 'category' => 'Space Exploration Tech'],
        ];
        
        $testData = [
            ['title' => 'Mars rover', 'category' => 'Planetary Science'],
            ['title' => 'Star death', 'category' => 'Stellar Physics'],
            ['title' => 'Rocket landing', 'category' => 'Space Exploration Tech'],
        ];
        
        $model = $service->fit($trainData);
        $metrics = $service->evaluate($testData, $model);
        
        $this->assertArrayHasKey('accuracy', $metrics);
        $this->assertArrayHasKey('confusion_matrix', $metrics);
        $this->assertGreaterThanOrEqual(0, $metrics['accuracy']);
        $this->assertLessThanOrEqual(1, $metrics['accuracy']);
    }
}