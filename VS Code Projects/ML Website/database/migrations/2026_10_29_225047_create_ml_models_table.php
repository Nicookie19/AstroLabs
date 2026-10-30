<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "linear_regression_orbit_all_confirmed"
            $table->json('parameters'); // coefficients, weights, etc.
            $table->json('metrics'); // R-squared, accuracy, confusion matrix, etc.
            $table->unsignedBigInteger('dataset_id')->index();
            $table->timestamp('trained_at');
            $table->timestamps();
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
};