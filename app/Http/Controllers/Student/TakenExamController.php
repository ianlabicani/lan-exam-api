<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamActivityLog;
use App\Models\TakenExam;
use App\Models\TakenExamAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TakenExamController extends Controller
{
    /**
     * Display all taken exams for the authenticated student
     */
    public function index()
    {
        $user = Auth::user();

        // OPTIMIZED: Select only necessary columns and use withCount for aggregates
        $takenExams = TakenExam::with(['exam:id,title,total_points,status,starts_at,ends_at'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($takenExam) {
                // Add computed properties
                $takenExam->is_ongoing = $takenExam->submitted_at === null;
                $takenExam->is_completed = $takenExam->submitted_at !== null;

                if ($takenExam->exam) {
                    $takenExam->percentage = $takenExam->exam->total_points > 0
                        ? round(($takenExam->total_points / $takenExam->exam->total_points) * 100, 2)
                        : 0;
                }

                return $takenExam;
            });

        return response()->json(['data' => $takenExams]);
    }

    /**
     * Start a new exam session (create)
     */
    public function create(Request $request)
    {
        $examId = $request->input('exam_id');

        if (! $examId) {
            return response()->json([
                'message' => 'Exam ID is required.',
            ], 422);
        }

        $exam = Exam::with('items')->findOrFail($examId);
        $user = Auth::user();

        // Check if exam is available
        if ($exam->status !== 'ongoing') {
            return response()->json([
                'message' => 'This exam is not currently available.',
            ], 403);
        }

        // Check if already taken
        $existingTakenExam = TakenExam::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingTakenExam && $existingTakenExam->submitted_at) {
            return response()->json([
                'message' => 'You have already submitted this exam.',
                'taken_exam_id' => $existingTakenExam->id,
            ], 403);
        }

        // If ongoing, return the existing taken exam
        if ($existingTakenExam) {
            return response()->json([
                'taken_exam_id' => $existingTakenExam->id,
                'message' => 'Exam already in progress.',
            ]);
        }

        // Create new taken exam
        $takenExam = TakenExam::create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'total_points' => 0,
        ]);

        // Load with relationships
        $takenExam->load(['exam.items', 'answers']);

        return response()->json([
            'data' => [
                'exam' => $exam,
                'taken_exam' => $takenExam,
            ],
            'message' => 'Exam created successfully.',
        ], 201);
    }

    /**
     * Continue an ongoing exam
     */
    public function continue($id)
    {
        $user = Auth::user();

        $takenExam = TakenExam::with(['exam.items', 'answers'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        // Check if already submitted
        if ($takenExam->submitted_at) {
            return response()->json(['info' => 'This exam has already been submitted.'], 403);
        }

        // Check if exam is still available
        if ($takenExam->exam->status !== 'ongoing') {
            return response()->json(['error' => 'This exam is no longer available.'], 403);
        }

        $exam = $takenExam->exam;

        return response()->json([
            'exam' => $exam,
            'taken_exam' => $takenExam,
        ]);
    }

    /**
     * Display a completed exam (read-only review)
     */
    public function show($id)
    {
        $user = Auth::user();

        $takenExam = TakenExam::with(['exam.items', 'answers.item'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if (! $takenExam->submitted_at) {
            return response()->json(['info' => 'Please complete and submit the exam first.'], 403);
        }

        $exam = $takenExam->exam;

        // Check if the student's submission has been graded
        if ($takenExam->status !== 'graded') {
            return response()->json([
                'pending' => true,
                'message' => 'Exam is pending grading.',
                'takenExam' => $takenExam,
                'exam' => $exam,
            ], 202);
        }

        // Additionally check if exam is closed (optional double-check)
        if ($exam->status !== 'closed') {
            return response()->json([
                'pending' => true,
                'message' => 'Exam is not yet closed.',
                'takenExam' => $takenExam,
                'exam' => $exam,
            ], 202);
        }

        // Calculate statistics (only shown when graded and exam is closed)
        $totalQuestions = $exam->items->count();
        $answeredQuestions = $takenExam->answers->count();
        $correctAnswers = $takenExam->answers->where('points_earned', '>', 0)->count();
        $percentage = $exam->total_points > 0
            ? round(($takenExam->total_points / $exam->total_points) * 100, 2)
            : 0;

        // Load activity logs for this exam session
        $activityLogs = ExamActivityLog::where('taken_exam_id', $takenExam->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'exam' => $exam,
            'taken_exam' => $takenExam,
            'total_questions' => $totalQuestions,
            'answered_questions' => $answeredQuestions,
            'correct_answers' => $correctAnswers,
            'percentage' => $percentage,
            'activity_logs' => $activityLogs,
        ]);
    }

    /**
     * Start exam (API endpoint - returns JSON)
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
        ]);

        $user = Auth::user();
        $exam = Exam::with('items')->findOrFail($validated['exam_id']);

        // Check if exam is available
        if ($exam->status !== 'ongoing') {
            return response()->json([
                'success' => false,
                'message' => 'This exam is not currently available.',
            ], 403);
        }

        // Check if already taken
        $existingTakenExam = TakenExam::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingTakenExam && $existingTakenExam->submitted_at) {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted this exam.',
            ], 403);
        }

        if ($existingTakenExam) {
            return response()->json([
                'success' => true,
                'taken_exam_id' => $existingTakenExam->id,
                'message' => 'Exam already started.',
            ]);
        }

        // Create new taken exam
        $takenExam = TakenExam::create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'total_points' => 0,
        ]);

        return response()->json([
            'success' => true,
            'taken_exam_id' => $takenExam->id,
            'exam' => $exam,
            'message' => 'Exam started successfully.',
        ]);
    }

    /**
     * Save answer (AJAX)
     */
    public function saveAnswer(Request $request, $id)
    {
        $user = Auth::user();

        $takenExam = TakenExam::where('user_id', $user->id)
            ->findOrFail($id);

        // Check if already submitted
        if ($takenExam->submitted_at) {
            return response()->json(['error' => 'Exam already submitted'], 403);
        }

        $validated = $request->validate([
            'item_id' => 'required|exists:exam_items,id',
            'answer' => 'required',
        ]);

        $answer = $validated['answer'];
        $itemId = $validated['item_id'];

        // For array answers, JSON encode them directly
        // Frontend already sends matching answers as objects: [{"left":"...", "right":"..."}]
        if (is_array($answer)) {
            $answer = json_encode($answer);
        }

        // Save or update answer
        $answerRecord = TakenExamAnswer::updateOrCreate(
            [
                'taken_exam_id' => $takenExam->id,
                'exam_item_id' => $itemId,
            ],
            [
                'answer' => $answer,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Answer saved successfully',
        ]);
    }

    /**
     * Save multiple answers in batch for better performance
     */
    public function saveAnswersBatch(Request $request, $id)
    {
        $user = Auth::user();

        $takenExam = TakenExam::where('user_id', $user->id)
            ->findOrFail($id);

        // Check if already submitted
        if ($takenExam->submitted_at) {
            return response()->json(['error' => 'Exam already submitted'], 403);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.item_id' => 'required|exists:exam_items,id',
            'answers.*.answer' => 'required',
        ]);

        // Use transaction for batch insert/update
        DB::beginTransaction();
        try {
            $savedCount = 0;

            foreach ($validated['answers'] as $answerData) {
                $answer = $answerData['answer'];

                // JSON-encode array answers directly
                // Frontend already sends matching answers as objects: [{"left":"...", "right":"..."}]
                if (is_array($answer)) {
                    $answer = json_encode($answer);
                }

                TakenExamAnswer::updateOrCreate(
                    [
                        'taken_exam_id' => $takenExam->id,
                        'exam_item_id' => $answerData['item_id'],
                    ],
                    [
                        'answer' => $answer,
                    ]
                );
                $savedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Answers saved successfully',
                'saved_count' => $savedCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Batch save failed: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to save answers',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit exam
     */
    public function submit(Request $request, $id)
    {
        $user = Auth::user();

        $takenExam = TakenExam::with('exam.items')
            ->where('user_id', $user->id)
            ->findOrFail($id);

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
            Log::error('Exam submission failed: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to submit exam. Please try again.',
            ], 500);
        }
    }

    /**
     * Log activity (tab switch, window blur)
     */
    public function logActivity(Request $request, $id)
    {
        $user = Auth::user();

        $takenExam = TakenExam::where('user_id', $user->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'event_type' => 'required',
            'timestamp' => 'required|date',
        ]);

        // Store activity in database
        ExamActivityLog::create([
            'taken_exam_id' => $takenExam->id,
            'student_id' => $user->id,
            'event_type' => $validated['event_type'],
            'details' => [
                'timestamp' => $validated['timestamp'],
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Activity logged',
        ]);
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
                // Score each correct pair individually
                if (! is_string($studentAnswer)) {
                    return 0;
                }

                $studentPairs = json_decode($studentAnswer, true);
                if (! is_array($studentPairs) || ! is_array($item->pairs)) {
                    return 0;
                }

                $correctCount = 0;
                // Student answers are objects: {"left": "...", "right": "..."}
                foreach ($studentPairs as $studentPair) {
                    if (! is_array($studentPair) || ! isset($studentPair['left'], $studentPair['right'])) {
                        continue;
                    }

                    // Find the correct right value for this left item
                    $correctRightValue = null;
                    foreach ($item->pairs as $pair) {
                        if ($pair['left'] === $studentPair['left']) {
                            $correctRightValue = $pair['right'];
                            break;
                        }
                    }

                    // Check if student's right value matches the correct one
                    if ($correctRightValue !== null && $studentPair['right'] === $correctRightValue) {
                        $correctCount++;
                    }
                }

                // Each correct pair is worth 1 point
                return $correctCount;

            default:
                return 0;
        }
    }
}
