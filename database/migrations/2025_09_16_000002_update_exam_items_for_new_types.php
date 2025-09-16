<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('exam_items', function (Blueprint $table) {
      if (!Schema::hasColumn('exam_items', 'pairs')) {
        $table->json('pairs')->nullable()->after('options');
      }
    });

    // Change answer column type from boolean to text (nullable)
    DB::statement("ALTER TABLE exam_items MODIFY answer TEXT NULL");

    // Extend enum for type to include new question types
    DB::statement("ALTER TABLE exam_items MODIFY type ENUM('mcq','truefalse','fillblank','shortanswer','essay','matching') NOT NULL");
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    // Revert enum to original set
    DB::statement("ALTER TABLE exam_items MODIFY type ENUM('mcq','truefalse','essay') NOT NULL");

    // Revert answer column back to boolean (tinyint(1))
    DB::statement("ALTER TABLE exam_items MODIFY answer TINYINT(1) NULL");

    Schema::table('exam_items', function (Blueprint $table) {
      if (Schema::hasColumn('exam_items', 'pairs')) {
        $table->dropColumn('pairs');
      }
    });
  }
};
