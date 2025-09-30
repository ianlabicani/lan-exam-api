<?php

use App\Http\Controllers\Teacher\ExamController;
use App\Http\Controllers\Teacher\ExamItemController;
use App\Http\Controllers\Teacher\TakenExamController;
use Illuminate\Support\Facades\Route;


Route::prefix('teacher')->middleware(['auth:sanctum'])->group(function () {
    Route::resource('exams', ExamController::class);
    Route::patch('exams/{exam}/status', [ExamController::class, 'updateStatus']);


    Route::get('exam-items/{exam}', [ExamItemController::class, 'index']);
    Route::post('exam-items/{exam}', [ExamItemController::class, 'store']);
    Route::put('exam-items/{examItem}', [ExamItemController::class, 'update']);
    Route::delete('exam-items/{examItem}', [ExamItemController::class, 'destroy']);

    Route::get('exams/{exam}/takenExams', [TakenExamController::class, 'index']);
    Route::get('exams/{exam}/takenExams/{takenExam}', [TakenExamController::class, 'show']);


});
