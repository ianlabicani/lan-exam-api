<?php

use App\Http\Controllers\Student\ExamController;
use Illuminate\Support\Facades\Route;


Route::prefix('student')->middleware(['auth:sanctum'])->group(function () {
    Route::resource('exams', ExamController::class);
});
