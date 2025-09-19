<?php

use App\Http\Controllers\Student\ExamController;
use App\Http\Controllers\Student\ExamItemController;
use App\Http\Controllers\Student\TakenExamAnswerController;
use App\Http\Controllers\Student\TakenExamController;
use Illuminate\Support\Facades\Route;


Route::prefix('student')->middleware(['auth:sanctum'])->group(function () {
    Route::resource('exams', ExamController::class);
    Route::get('/exams/{exam}/items', [ExamItemController::class, 'index']);
    Route::post('/exams/{exam}/take', [TakenExamController::class, 'store']);
    Route::post('/taken-exams/{id}/submit', [TakenExamController::class, 'finish']);

    // Answers
    Route::get('/taken-exams', [TakenExamController::class, 'index']);
    Route::post('/taken-exams/{takenExam}/answers', [TakenExamAnswerController::class, 'store']);
    Route::get('/taken-exams/{takenExamId}', [TakenExamController::class, 'show']);

});
