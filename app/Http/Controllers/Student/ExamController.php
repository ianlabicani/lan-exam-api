<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user();
        $year = $student->year;
        $section = $student->section;

        $exams = Exam::where('year', $year)
            ->where('section', $section)
            ->get();

        return response()->json([
            'exams' => $exams
        ]);
    }
}
