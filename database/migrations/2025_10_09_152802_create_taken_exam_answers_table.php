<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('taken_exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taken_exam_id')->constrained('taken_exams')->cascadeOnDelete();
            $table->foreignId('exam_item_id')->constrained('exam_items')->cascadeOnDelete();
            $table->text('answer')->nullable(); // could be index (for mcq), boolean (true/false), or string (essay)
            $table->integer('points_earned')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taken_exam_answers');
    }
};
