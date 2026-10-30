<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('koi_candidates', function (Blueprint $table) {
            // NASA's cumulative catalog includes large false-positive radius estimates.
            $table->decimal('planet_radius_earths', 12, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('koi_candidates', function (Blueprint $table) {
            $table->decimal('planet_radius_earths', 8, 4)->nullable()->change();
        });
    }
};
