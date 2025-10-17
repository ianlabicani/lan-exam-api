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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->json('year')->nullable();
            $table->json('sections')->nullable();
            $table->enum('status', ['draft', 'ready', 'published', 'ongoing', 'closed', 'graded', 'archived'])->default('draft');
            $table->integer('total_points')->default(0);
            $table->json('tos')->nullable()->comment('Table of Specifications');
            $table->timestamps();
        });

        // Pivot table for exam-teacher relationship
        Schema::create('exam_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_teacher');
        Schema::dropIfExists('exams');
    }
};
