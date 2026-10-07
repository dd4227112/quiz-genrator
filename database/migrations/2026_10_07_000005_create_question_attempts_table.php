<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->jsonb('response');
            $table->boolean('is_correct');
            $table->unsignedTinyInteger('score');
            $table->text('feedback')->nullable();
            $table->string('graded_by');
            $table->timestamps();

            $table->index(['question_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_attempts');
    }
};
