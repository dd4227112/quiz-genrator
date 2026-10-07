<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_set_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('position');
            $table->text('prompt');
            $table->jsonb('payload');
            $table->jsonb('answer');
            $table->text('explanation');
            $table->timestamps();

            $table->index(['question_set_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
