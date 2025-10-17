<?php

use App\Http\Controllers\Student\ExamController;
use App\Http\Controllers\Student\TakenExamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('student.exams.index');
    })->name('dashboard');

    // Exam Routes for Students - Available exams to browse
    Route::prefix('exams')->name('exams.')->group(function () {
        // List available exams
        Route::get('/', [ExamController::class, 'index'])->name('index');
    });

    // Taken Exams - New Pattern for exam taking and history
    Route::prefix('taken-exams')->name('taken-exams.')->group(function () {
        // View all taken exams (history)
        Route::get('/', [TakenExamController::class, 'index'])->name('index');

        // Start new exam
        Route::get('/create', [TakenExamController::class, 'create'])->name('create');

        // Continue ongoing exam
        Route::get('/{id}/continue', [TakenExamController::class, 'continue'])->name('continue');

        // View completed exam (review)
        Route::get('/{id}', [TakenExamController::class, 'show'])->name('show');

        // API endpoints
        Route::post('/{id}/start', [TakenExamController::class, 'start'])->name('start');
        Route::post('/{id}/save-answer', [TakenExamController::class, 'saveAnswer'])->name('save-answer');
        Route::post('/{id}/save-answers-batch', [TakenExamController::class, 'saveAnswersBatch'])->name('save-answers-batch');
        Route::post('/{id}/submit', [TakenExamController::class, 'submit'])->name('submit');
        Route::post('/{id}/activity', [TakenExamController::class, 'logActivity'])->name('activity');
    });
});
