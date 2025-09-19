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

        // Fetch by year, then case-insensitive filter on JSON sections array
        $exams = Exam::query()
            ->where('year', $year)
            ->get()
            ->filter(function (Exam $exam) use ($section) {
                $sections = (array) ($exam->sections ?? []);
                $needle = strtolower((string) $section);
                foreach ($sections as $s) {
                    if (strtolower((string) $s) === $needle) {
                        return true;
                    }
                }
                return false;
            })
            ->values();

        $exams->load('takenExams');


        return response()->json([
            'data' => $exams
        ]);
    }

    public function show(Request $request, Exam $exam)
    {

        $user = $request->user();


        $takenExam = $user->takenExams()->where('exam_id', $exam->id)->first();


        return response()->json([
            'exam' => $exam,
            'takenExam' => $takenExam
        ]);
    }
}
