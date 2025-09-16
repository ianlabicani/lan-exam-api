<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['mcq', 'truefalse', 'essay', 'fillblank', 'shortanswer', 'matching']);
            $table->text('question');
            $table->integer('points');
            $table->text('expected_answer')->nullable();
            $table->text('answer')->nullable();
            $table->json('options')->nullable();
            $table->json('pairs')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_items');
    }
};
