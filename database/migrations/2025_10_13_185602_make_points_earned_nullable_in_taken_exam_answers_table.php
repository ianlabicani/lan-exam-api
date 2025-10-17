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
        Schema::table('taken_exam_answers', function (Blueprint $table) {
            $table->integer('points_earned')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taken_exam_answers', function (Blueprint $table) {
            $table->integer('points_earned')->default(0)->change();
        });
    }
};
