<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('explorer_id')->index();
            $table->string('mission_key');
            $table->unsignedBigInteger('dataset_id')->nullable()->index();
            $table->json('inputs');
            $table->json('output');
            $table->json('user_guess')->nullable();
            $table->integer('points')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_runs');
    }
};