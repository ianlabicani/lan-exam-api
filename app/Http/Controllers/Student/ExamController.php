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

        // Fetch by year with taken exams for this student, then case-insensitive filter on JSON sections array
        $exams = Exam::query()
            ->where('year', $year)
            ->with([
                'takenExams' => function ($query) use ($student) {
                    $query->where('user_id', $student->id);
                }
            ])
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
            ->map(function (Exam $exam) {
                // Add taken_exam property (first/only taken exam for this student)
                $exam->taken_exam = $exam->takenExams->first();
                // Clean up the takenExams collection to avoid duplication
                unset($exam->takenExams);
                return $exam;
            })
            ->values();

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
