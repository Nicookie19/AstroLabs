<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exoplanets', function (Blueprint $table) {
            // Keep extreme but published catalog measurements rather than rejecting them on import.
            $table->decimal('orbital_period_days', 18, 6)->nullable()->change();
            $table->decimal('semi_major_axis_au', 18, 8)->nullable()->change();
            $table->decimal('star_mass_solar', 18, 6)->nullable()->change();
            $table->decimal('radius_earths', 18, 6)->nullable()->change();
            $table->decimal('mass_earths', 18, 6)->nullable()->change();
            $table->decimal('bulk_density_g_cm3', 18, 6)->nullable()->change();
            $table->decimal('equilibrium_temp_k', 18, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('exoplanets', function (Blueprint $table) {
            $table->decimal('orbital_period_days', 12, 4)->nullable()->change();
            $table->decimal('semi_major_axis_au', 12, 6)->nullable()->change();
            $table->decimal('star_mass_solar', 8, 4)->nullable()->change();
            $table->decimal('radius_earths', 8, 4)->nullable()->change();
            $table->decimal('mass_earths', 12, 4)->nullable()->change();
            $table->decimal('bulk_density_g_cm3', 8, 4)->nullable()->change();
            $table->decimal('equilibrium_temp_k', 8, 2)->nullable()->change();
        });
    }
};
