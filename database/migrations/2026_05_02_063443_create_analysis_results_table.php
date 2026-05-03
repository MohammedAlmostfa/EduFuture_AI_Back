<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->longText('simple_explanation')->nullable();
            $table->json('key_ideas')->nullable();
            $table->longText('historical_context')->nullable();
            $table->longText('market_usage')->nullable();
            $table->json('related_jobs')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_results');
    }
};
