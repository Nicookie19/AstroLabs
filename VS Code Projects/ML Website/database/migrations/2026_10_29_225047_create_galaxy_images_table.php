<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galaxy_images', function (Blueprint $table) {
            $table->id();
            $table->string('source_id');
            $table->enum('label', ['Elliptical', 'Spiral', 'Irregular', 'Edge-on disk']);
            $table->string('original_label');
            $table->string('image_path');
            $table->json('pixels'); // 256 floats for 16x16 grayscale
            $table->enum('split', ['train', 'test']);
            $table->unsignedBigInteger('dataset_id')->nullable()->index();
            $table->index('label');
            $table->index('split');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galaxy_images');
    }
};