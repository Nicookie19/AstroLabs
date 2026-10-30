<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exoplanets', function (Blueprint $table) {
            $table->decimal('mass_earths', 12, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('exoplanets', function (Blueprint $table) {
            $table->decimal('mass_earths', 8, 4)->nullable()->change();
        });
    }
};