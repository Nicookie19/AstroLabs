<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->string('mission_key'); // orbit, forum, exoplanets, transit, telescope, galaxy
            $table->string('key'); // unique key like "all_confirmed", "kepler", "tess", etc.
            $table->string('title');
            $table->text('description');
            $table->string('publisher');
            $table->string('url');
            $table->string('license');
            $table->integer('row_count');
            $table->timestamp('retrieved_at');
            $table->text('citation_text');
            $table->json('feature_schema'); // feature names, units, min, max, step
            $table->json('label_names')->nullable(); // for classification datasets
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->unique(['mission_key', 'key']);
            $table->index('mission_key');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};