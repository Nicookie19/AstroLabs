<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('koi_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('kepoi_name');
            $table->enum('disposition', ['CONFIRMED', 'FALSE POSITIVE']);
            $table->decimal('orbital_period_days', 12, 4)->nullable();
            $table->decimal('transit_depth_ppm', 12, 4)->nullable();
            $table->decimal('transit_duration_hours', 10, 4)->nullable();
            $table->decimal('signal_to_noise', 10, 4)->nullable();
            $table->decimal('planet_radius_earths', 8, 4)->nullable();
            $table->unsignedBigInteger('dataset_id')->nullable()->index();
            $table->index('disposition');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('koi_candidates');
    }
};