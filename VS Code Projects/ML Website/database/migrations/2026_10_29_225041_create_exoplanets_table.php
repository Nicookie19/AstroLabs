<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exoplanets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host_star');
            $table->decimal('orbital_period_days', 12, 4)->nullable();
            $table->decimal('semi_major_axis_au', 12, 6)->nullable();
            $table->decimal('star_mass_solar', 8, 4)->nullable();
            $table->decimal('radius_earths', 8, 4)->nullable();
            $table->decimal('mass_earths', 8, 4)->nullable();
            $table->decimal('bulk_density_g_cm3', 8, 4)->nullable();
            $table->decimal('equilibrium_temp_k', 8, 2)->nullable();
            $table->integer('discovery_year')->nullable();
            $table->string('discovery_method')->nullable();
            $table->unsignedBigInteger('dataset_id')->nullable()->index();
            $table->index(['host_star', 'name']);
            $table->index('discovery_method');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exoplanets');
    }
};