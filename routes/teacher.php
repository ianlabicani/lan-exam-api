<?php

use App\Http\Controllers\Teacher\ExamController;
use Illuminate\Support\Facades\Route;


Route::prefix('teacher')->middleware(['auth:sanctum'])->group(function () {
    Route::resource('exams', ExamController::class);
});
