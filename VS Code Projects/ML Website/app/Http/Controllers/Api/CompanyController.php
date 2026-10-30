<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactMessageRequest;
use App\Models\CompanyService;
use App\Models\ContactMessage;
use App\Models\FaqItem;
use App\Models\RoadmapItem;
use App\Models\TeamMember;

class CompanyController extends Controller
{
    public function home()
    {
        $services = CompanyService::orderBy('sort_order')->get();
        $team = TeamMember::orderBy('sort_order')->get();
        $missions = [
            ['key' => 'orbit', 'title' => 'Orbit Calculator', 'algorithm' => 'Linear Regression', 'description' => 'Predict orbital periods using Kepler\'s third law'],
            ['key' => 'forum', 'title' => 'Forum Sorter', 'algorithm' => 'Naive Bayes', 'description' => 'Classify space questions by topic'],
            ['key' => 'exoplanets', 'title' => 'Exoplanet Twin Finder', 'algorithm' => 'K-Nearest Neighbors', 'description' => 'Find similar exoplanets by physical properties'],
            ['key' => 'transit', 'title' => 'Transit Hunter', 'algorithm' => 'Support Vector Machine', 'description' => 'Detect exoplanet transits in light curves'],
            ['key' => 'telescope', 'title' => 'Telescope Setup Wizard', 'algorithm' => 'Decision Tree', 'description' => 'Get optimal telescope settings for any target'],
            ['key' => 'galaxy', 'title' => 'Galaxy Classifier', 'algorithm' => 'Neural Network', 'description' => 'Classify galaxy morphology from images'],
        ];

        return response()->json([
            'company_profile' => 'Astrolabs: Your interactive gateway to space science, where real astronomical data meets machine learning.',
            'data_driven_needs' => 'Astrolabs needs to process massive astronomical datasets, classify celestial objects, predict orbital mechanics, and guide observational strategies—all requiring robust machine learning.',
            'missions' => $missions,
            'services' => $services,
            'team' => $team,
        ]);
    }

    public function about()
    {
        $services = CompanyService::orderBy('sort_order')->get();
        $team = TeamMember::orderBy('sort_order')->get();
        $faqs = FaqItem::orderBy('sort_order')->get();
        $roadmap = RoadmapItem::orderBy('sort_order')->get();

        $algorithmMapping = [
            'Orbit Lab' => 'Linear Regression',
            'Telescope Academy' => 'Decision Tree',
            'Exoplanet Atlas' => 'K-Nearest Neighbors',
            'Transit Observatory' => 'Support Vector Machine',
            'Galaxy Zoo ML' => 'Neural Network',
            'Forum Intelligence' => 'Naive Bayes',
        ];

        return response()->json([
            'story' => 'Founded in 2024 as a fictional educational venture for ELEC026.23, Astrolabs was born from a passion for making space science accessible. We believe the best way to learn astronomy is by doing—using real data, real algorithms, and real telescopes.',
            'mission' => 'To democratize space science education through interactive, data-driven experiences that teach both astronomy and machine learning.',
            'vision' => 'A world where every student can explore the cosmos with professional-grade tools and understand the algorithms powering modern astrophysics.',
            'values' => [
                'Scientific Integrity: Real data, honest limitations, no fabricated results.',
                'Educational Excellence: Learn by doing, with full transparency.',
                'Open Access: All data sources cited, all algorithms explainable.',
            ],
            'services' => $services,
            'algorithm_mapping' => $algorithmMapping,
            'roadmap' => $roadmap,
            'team' => $team,
            'faqs' => $faqs,
        ]);
    }

    public function contact(ContactMessageRequest $request)
    {
        $message = ContactMessage::create($request->validated());
        
        return response()->json([
            'success' => true,
            'message' => 'Thank you for contacting Astrolabs! We\'ll get back to you soon.',
            'id' => $message->id,
        ]);
    }
}