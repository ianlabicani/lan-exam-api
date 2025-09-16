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
        Schema::table('exam_items', function (Blueprint $table) {
            $table->enum('level', ['easy', 'moderate', 'difficult'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_items', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
