<?php

use App\Http\Controllers\Teacher\ExamController;
use App\Http\Controllers\Teacher\ExamItemController;
use Illuminate\Support\Facades\Route;


Route::prefix('teacher')->middleware(['auth:sanctum'])->group(function () {
    Route::resource('exams', ExamController::class);
    Route::resource('exams.items', ExamItemController::class);
});
