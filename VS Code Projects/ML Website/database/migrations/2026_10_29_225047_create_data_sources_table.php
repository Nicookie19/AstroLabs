<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g., "nasa_exoplanet_archive_pscomppars"
            $table->string('title');
            $table->string('publisher');
            $table->string('url');
            $table->string('license');
            $table->timestamp('retrieved_at');
            $table->integer('row_count');
            $table->text('citation_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};