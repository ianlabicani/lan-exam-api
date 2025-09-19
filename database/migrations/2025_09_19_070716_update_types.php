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
        Schema::table('taken_exam_answers', function (Blueprint $table) {
            $table->enum('type', ['mcq', 'truefalse', 'essay', 'fill_blank', 'shortanswer', 'matching'])
                ->after('exam_item_id')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taken_exam_answers', function (Blueprint $table) {
            $table->enum('type', ['mcq', 'truefalse', 'essay'])
                ->after('exam_item_id')
                ->change();
        });
    }
};
