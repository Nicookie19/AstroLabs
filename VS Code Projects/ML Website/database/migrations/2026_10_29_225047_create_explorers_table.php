<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('explorers', function (Blueprint $table) {
            $table->id();
            $table->string('callsign', 20);
            $table->enum('level', ['Beginner', 'Intermediate', 'Advanced']);
            $table->string('session_token')->unique();
            $table->integer('total_points')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explorers');
    }
};