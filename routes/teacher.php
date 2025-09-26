<?php

use App\Http\Controllers\Teacher\ExamController;
use App\Http\Controllers\Teacher\ExamItemController;
use App\Http\Controllers\Teacher\TakenExamController;
use Illuminate\Support\Facades\Route;


Route::prefix('teacher')->middleware(['auth:sanctum'])->group(function () {
    Route::resource('exams', ExamController::class);
    Route::patch('exams/{exam}/status', [ExamController::class, 'updateStatus']);
    Route::resource('exams.items', ExamItemController::class);
    Route::get('exams/{exam}/takenExams', [TakenExamController::class, 'index']);
    Route::get('exams/{exam}/takenExams/{takenExam}', [TakenExamController::class, 'show']);
});
