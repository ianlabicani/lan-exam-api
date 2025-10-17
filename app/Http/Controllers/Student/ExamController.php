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
    public function index()
    {
        $user = Auth::user();

        // OPTIMIZED: Get published exams with eager loading
        $exams = Exam::where('status', 'ongoing')
            ->where(function ($query) use ($user) {
                // Check if student's year is in the year array
                $query->whereJsonContains('year', $user->year)
                    ->orWhereRaw('JSON_CONTAINS(year, ?)', [json_encode($user->year)]);
            })
            ->where(function ($query) use ($user) {
                // Check if student's section is in the sections array
                $query->whereJsonContains('sections', $user->section)
                    ->orWhereRaw('JSON_CONTAINS(sections, ?)', [json_encode($user->section)]);
            })
            ->withCount('items') // Use withCount instead of loading all items
            ->with([
                'takenExams' => function ($query) use ($user) {
                    // Only load this user's taken exam
                    $query->where('user_id', $user->id);
                }
            ])
            ->orderBy('starts_at', 'desc')
            ->get()
            ->map(function ($exam) use ($user) {
                // Check if student has already taken this exam
                $takenExam = $exam->takenExams->first();

                $exam->taken = $takenExam !== null;
                $exam->taken_exam = $takenExam;
                $exam->is_available = $this->isExamAvailable($exam);

                return $exam;
            });

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
     * Submit exam and calculate score
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
            // Auto-grade objective questions
            foreach ($exam->items as $item) {
                $answer = TakenExamAnswer::where('taken_exam_id', $takenExam->id)
                    ->where('exam_item_id', $item->id)
                    ->first();

                if ($answer) {
                    $pointsEarned = $this->gradeAnswer($item, $answer->answer);
                    $answer->update(['points_earned' => $pointsEarned]);
                }
            }

            // Calculate total points
            $totalPoints = $takenExam->answers()->sum('points_earned');

            // Mark as submitted
            $takenExam->update([
                'submitted_at' => now(),
                'total_points' => $totalPoints,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Exam submitted successfully!',
                'total_points' => $totalPoints,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Failed to submit exam. Please try again.'
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
        // Exam is available if status is 'ongoing'
        // Teacher can manually set it to ongoing OR it can auto-transition based on schedule
        return $exam->status === 'ongoing';
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
                if (!is_string($studentAnswer)) {
                    return 0;
                }

                $studentPairs = json_decode($studentAnswer, true);
                if (!is_array($studentPairs) || !is_array($item->pairs)) {
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
