<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_progress', function (Blueprint $table) {
            $table->id();
            $table->string('session_token')->index();
            $table->string('mission_key'); // orbit, forum, exoplanets, transit, telescope, galaxy
            $table->timestamp('completed_at');
            $table->index(['session_token', 'mission_key']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_progress');
    }
};