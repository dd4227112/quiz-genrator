<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('question_set_topic', function (Blueprint $table) {
            $table->foreignId('question_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(0);

            $table->primary(['question_set_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_set_topic');
        Schema::dropIfExists('topics');
    }
};
