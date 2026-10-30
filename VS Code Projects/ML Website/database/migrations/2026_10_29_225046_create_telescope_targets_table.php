<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telescope_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('catalog_id')->nullable();
            $table->enum('category', ['Moon', 'Planet', 'Nebula', 'Cluster', 'Galaxy']);
            $table->tinyInteger('distance_rank'); // 0=Moon, 1=Planet, 2=Outside solar system inside galaxy, 3=Beyond galaxy
            $table->decimal('apparent_magnitude', 6, 2)->nullable();
            $table->string('constellation')->nullable();
            $table->unsignedBigInteger('dataset_id')->nullable()->index();
            $table->index('category');
            $table->index('distance_rank');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telescope_targets');
    }
};