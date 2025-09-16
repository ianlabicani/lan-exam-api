<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\TakenExam;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user();
        $year = $student->year;
        $section = $student->section;

        $exams = Exam::query()
            ->where('year', $year)
            ->whereJsonContains('sections', $section)
            ->get();

        $exams->load('takenExams');


        return response()->json([
            'exams' => $exams
        ]);
    }
}
