<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191);
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('context')->nullable();
            $table->timestamps();

            $table->unique(['key', 'language_id']);
            $table->index(['language_id']);
            $table->index(['updated_at']);
            $table->index(['key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
