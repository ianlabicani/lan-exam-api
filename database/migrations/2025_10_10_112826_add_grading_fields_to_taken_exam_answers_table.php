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
            $table->text('feedback')->nullable()->after('points_earned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taken_exam_answers', function (Blueprint $table) {
            $table->dropColumn('feedback');
        });
    }
};
