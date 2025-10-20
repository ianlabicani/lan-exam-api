<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\TakenExam;
use App\Models\TakenExamAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    /**
     * Display available exams for the student
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        $exams = Exam::whereIn('status', ['published', 'ongoing'])
            ->where(function ($query) use ($user): void {
                $query->whereJsonContains('year', $user->year)
                    ->orWhereRaw('JSON_CONTAINS(year, ?)', [json_encode($user->year)]);
            })
            ->where(function ($query) use ($user): void {
                $query->whereJsonContains('sections', $user->section)
                    ->orWhereRaw('JSON_CONTAINS(sections, ?)', [json_encode($user->section)]);
            })
            ->withCount('items')
            ->with([
                'takenExams' => function ($query) use ($user): void {
                    $query->where('user_id', $user->id);
                },
            ])
            ->orderBy('starts_at', 'desc')
            ->get()
            ->makeHidden('takenExams')
            ->filter(function ($exam) {
                // Return exam if:
                // 1. Student has NO taken exam record (hasn't started yet)
                // 2. Student has a taken exam but it hasn't been submitted yet
                return $exam->takenExams->isEmpty() || ! $exam->takenExams->first()->submitted_at;
            })
            ->map(function ($exam) {
                return [
                    ...$exam->toArray(),
                    'taken_exam' => $exam->takenExams->isNotEmpty() ? $exam->takenExams->first() : null,
                ];
            })
            ->values();

        return response()->json(['data' => $exams]);
    }

    /**
     * Show exam taking interface
     */
    public function take($id)
    {
        $user = Auth::user();
        $exam = Exam::with('items')->findOrFail($id);

        // Verify exam is available
        if (! $this->isExamAvailable($exam)) {
            return response()->json(['error' => 'This exam is not currently available.'], 403);
        }

        // Check if student already took this exam
        $takenExam = TakenExam::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->first();

        if ($takenExam && $takenExam->submitted_at) {
            return response()->json(['info' => 'You have already submitted this exam.'], 403);
        }

        // If not started, create taken exam record
        if (! $takenExam) {
            $takenExam = TakenExam::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'started_at' => now(),
                'total_points' => 0,
            ]);
        }

        // Load existing answers
        $answers = $takenExam->answers()->with('item')->get()->keyBy('exam_item_id');

        return response()->json([
            'exam' => $exam,
            'taken_exam' => $takenExam,
            'answers' => $answers,
        ]);
    }

    /**
     * Save student answer (AJAX)
     */
    public function saveAnswer(Request $request, $id)
    {
        $user = Auth::user();
        $exam = Exam::findOrFail($id);

        $validated = $request->validate([
            'item_id' => 'required|exists:exam_items,id',
            'answer' => 'required',
        ]);

        $takenExam = TakenExam::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Check if already submitted
        if ($takenExam->submitted_at) {
            return response()->json(['error' => 'Exam already submitted'], 403);
        }

        // Save or update answer
        $answer = TakenExamAnswer::updateOrCreate(
            [
                'taken_exam_id' => $takenExam->id,
                'exam_item_id' => $validated['item_id'],
            ],
            [
                'answer' => $validated['answer'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Answer saved successfully',
        ]);
    }

    /**
     * Submit exam
     */
    public function submit(Request $request, $id)
    {
        $user = Auth::user();
        $exam = Exam::with('items')->findOrFail($id);

        $takenExam = TakenExam::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Check if already submitted
        if ($takenExam->submitted_at) {
            return response()->json(['info' => 'You have already submitted this exam.'], 403);
        }

        DB::beginTransaction();
        try {
            // Mark as submitted without grading
            $takenExam->update([
                'submitted_at' => now(),
                'status' => 'submitted',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Exam submitted successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Failed to submit exam. Please try again.',
            ], 500);
        }
    }

    /**
     * Show exam results
     */
    public function results($id)
    {
        $user = Auth::user();
        $exam = Exam::with('items')->findOrFail($id);

        $takenExam = TakenExam::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->with('answers.item')
            ->firstOrFail();

        if (! $takenExam->submitted_at) {
            return response()->json(['info' => 'Please complete and submit the exam first.'], 403);
        }

        // Only show results if graded
        if ($takenExam->status !== 'graded') {
            return response()->json([
                'pending' => true,
                'message' => 'Your exam is pending grading. Results will be available soon.',
            ], 202);
        }

        // Calculate statistics
        $totalQuestions = $exam->items->count();
        $answeredQuestions = $takenExam->answers->count();
        $correctAnswers = $takenExam->answers->where('points_earned', '>', 0)->count();
        $percentage = $exam->total_points > 0 ? ($takenExam->total_points / $exam->total_points) * 100 : 0;

        return response()->json([
            'exam' => $exam,
            'taken_exam' => $takenExam,
            'total_questions' => $totalQuestions,
            'answered_questions' => $answeredQuestions,
            'correct_answers' => $correctAnswers,
            'percentage' => $percentage,
        ]);
    }

    /**
     * Check if exam is currently available
     */
    private function isExamAvailable($exam)
    {
        // Exam is available if status is 'published' or 'ongoing'
        return in_array($exam->status, ['published', 'ongoing']);
    }

    /**
     * Grade a single answer
     */
    private function gradeAnswer($item, $studentAnswer)
    {
        switch ($item->type) {
            case 'mcq':
                $correctOption = null;
                foreach ($item->options as $index => $option) {
                    if (isset($option['correct']) && $option['correct']) {
                        $correctOption = $index;
                        break;
                    }
                }

                return (int) $studentAnswer === $correctOption ? $item->points : 0;

            case 'truefalse':
                $expected = strtolower(trim($item->answer));
                $student = strtolower(trim($studentAnswer));

                return $expected === $student ? $item->points : 0;

            case 'fillblank':
            case 'fill_blank':
                $expected = strtolower(trim($item->expected_answer));
                $student = strtolower(trim($studentAnswer));

                return $expected === $student ? $item->points : 0;

            case 'shortanswer':
            case 'essay':
                // Manual grading required - return null so it can be detected as ungraded
                return null;

            case 'matching':
                // Score each correct pair individually (1 point per pair)
                if (! is_string($studentAnswer)) {
                    return 0;
                }

                $studentPairs = json_decode($studentAnswer, true);
                if (! is_array($studentPairs) || ! is_array($item->pairs)) {
                    return 0;
                }

                $correctCount = 0;
                foreach ($studentPairs as $leftIndex => $rightIndex) {
                    // Check if this pair is correct
                    if (isset($item->pairs[$leftIndex]) && $item->pairs[$leftIndex]['right'] === $item->pairs[$rightIndex]['right']) {
                        $correctCount++;
                    }
                }

                // Each pair is worth 1 point
                return $correctCount;

            default:
                return 0;
        }
    }
}
