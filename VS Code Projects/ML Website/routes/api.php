<?php

use App\Http\Controllers\Api\OrbitMissionController;
use App\Http\Controllers\Api\ForumMissionController;
use App\Http\Controllers\Api\ExoplanetMissionController;
use App\Http\Controllers\Api\TransitMissionController;
use App\Http\Controllers\Api\TelescopeMissionController;
use App\Http\Controllers\Api\GalaxyMissionController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DatasetController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ExplorerController;
use Illuminate\Support\Facades\Route;

Route::prefix('missions')->group(function () {
    Route::get('/orbit/dataset', [OrbitMissionController::class, 'dataset']);
    Route::post('/orbit/predict', [OrbitMissionController::class, 'predict']);
    Route::post('/orbit/compare', [OrbitMissionController::class, 'compareDatasets']);

    Route::get('/forum/dataset', [ForumMissionController::class, 'dataset']);
    Route::post('/forum/classify', [ForumMissionController::class, 'classify']);

    Route::get('/exoplanets/dataset', [ExoplanetMissionController::class, 'dataset']);
    Route::post('/exoplanets/neighbors', [ExoplanetMissionController::class, 'neighbors']);

    Route::get('/transit/dataset', [TransitMissionController::class, 'dataset']);
    Route::post('/transit/classify', [TransitMissionController::class, 'classify']);

    Route::get('/telescope/targets', [TelescopeMissionController::class, 'targets']);
    Route::post('/telescope/recommend', [TelescopeMissionController::class, 'recommend']);

    Route::get('/galaxy/gallery', [GalaxyMissionController::class, 'gallery']);
    Route::post('/galaxy/classify', [GalaxyMissionController::class, 'classify']);
    Route::post('/galaxy/reveal', [GalaxyMissionController::class, 'reveal']);
    Route::get('/galaxy/metrics', [GalaxyMissionController::class, 'metrics']);
});

Route::prefix('company')->group(function () {
    Route::get('/home', [CompanyController::class, 'home']);
    Route::get('/about', [CompanyController::class, 'about']);
    Route::post('/contact', [CompanyController::class, 'contact']);
});

Route::prefix('datasets')->group(function () {
    Route::get('/', [DatasetController::class, 'index']);
    Route::get('/{missionKey}/{datasetKey}', [DatasetController::class, 'show']);
});

Route::prefix('progress')->group(function () {
    Route::get('/', [ProgressController::class, 'index']);
    Route::get('/reload/{runId}', [ProgressController::class, 'reload']);
});

Route::prefix('explorer')->group(function () {
    Route::post('/onboard', [ExplorerController::class, 'onboard']);
    Route::get('/me', [ExplorerController::class, 'me']);
    Route::put('/me', [ExplorerController::class, 'update']);
});