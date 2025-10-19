<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamActivityLog;
use App\Models\TakenExam;
use App\Models\TakenExamAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GradingController extends Controller
{
    /**
     * Display the grading interface for a specific taken exam (JSON API)
     */
    public function show($takenExamId)
    {
        $user = Auth::user();

        // Load the taken exam with all necessary relationships
        $takenExam = TakenExam::with([
            'exam.items',
            'exam.teachers',
            'answers.item',
            'user',
        ])->findOrFail($takenExamId);

        // Verify the authenticated teacher is assigned to this exam
        $isAssignedTeacher = $takenExam->exam->teachers()->where('teacher_id', $user->id)->exists();

        if (! $isAssignedTeacher) {
            return response()->json([
                'error' => 'Unauthorized access to this exam submission. You are not assigned as a teacher for this exam.',
            ], 403);
        }

        // Check if the exam has been submitted
        if (! $takenExam->submitted_at) {
            return response()->json([
                'error' => 'This exam has not been submitted yet.',
            ], 422);
        }

        $exam = $takenExam->exam;
        $student = $takenExam->user;

        // Organize items that need manual grading
        $itemsNeedingGrading = $takenExam->answers->filter(function ($answer) {
            return in_array($answer->item->type, ['essay', 'shortanswer']) &&
                   $answer->points_earned === null;
        })->values();

        // Calculate statistics
        $totalItems = $exam->items->count();
        $autoGradedItems = $takenExam->answers->whereNotIn('item.type', ['essay', 'shortanswer'])->count();
        $manualGradedItems = $takenExam->answers->whereIn('item.type', ['essay', 'shortanswer'])
            ->whereNotNull('points_earned')->count();
        $pendingGradingItems = $itemsNeedingGrading->count();

        $autoGradedScore = $takenExam->answers->whereNotIn('item.type', ['essay', 'shortanswer'])
            ->sum('points_earned');
        $manualGradedScore = $takenExam->answers->whereIn('item.type', ['essay', 'shortanswer'])
            ->sum('points_earned');

        // Create a simple map of graded items (for manual grading items only)
        $gradedItems = $takenExam->answers
            ->filter(function ($answer) {
                return in_array($answer->item->type, ['essay', 'shortanswer']) &&
                       $answer->points_earned !== null;
            })
            ->pluck('exam_item_id')
            ->mapWithKeys(function ($itemId) {
                return [$itemId => true];
            });

        // Load activity logs for this exam session
        $activityLogs = ExamActivityLog::where('taken_exam_id', $takenExam->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'takenExam' => $takenExam,
            'exam' => $exam,
            'student' => $student,
            'itemsNeedingGrading' => $itemsNeedingGrading,
            'totalItems' => $totalItems,
            'autoGradedItems' => $autoGradedItems,
            'manualGradedItems' => $manualGradedItems,
            'pendingGradingItems' => $pendingGradingItems,
            'autoGradedScore' => $autoGradedScore,
            'manualGradedScore' => $manualGradedScore,
            'gradedItems' => $gradedItems,
            'activityLogs' => $activityLogs,
        ]);
    }

    /**
     * Update the score for a specific answer item
     */
    public function updateScore(Request $request, $takenExamId, $itemId)
    {
        $user = Auth::user();

        // Find the taken exam and verify ownership
        $takenExam = TakenExam::with('exam.teachers')->findOrFail($takenExamId);

        // Verify the authenticated teacher is assigned to this exam
        $isAssignedTeacher = $takenExam->exam->teachers()->where('teacher_id', $user->id)->exists();

        if (! $isAssignedTeacher) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Find the answer
        $answer = TakenExamAnswer::with('item')
            ->where('taken_exam_id', $takenExamId)
            ->where('exam_item_id', $itemId)
            ->firstOrFail();

        // Validate the score
        $validated = $request->validate([
            'teacher_score' => 'required|numeric|min:0|max:'.$answer->item->points,
            'feedback' => 'nullable|string|max:1000',
        ]);

        // Update the answer
        $answer->update([
            'points_earned' => $validated['teacher_score'],
            'feedback' => $validated['feedback'] ?? null,
        ]);

        // Recalculate total score
        $this->recalculateTotalScore($takenExam);

        return response()->json([
            'success' => true,
            'message' => 'Item graded successfully',
            'updated_item' => [
                'id' => $answer->id,
                'exam_item_id' => $answer->exam_item_id,
                'points_earned' => $answer->points_earned,
                'feedback' => $answer->feedback,
            ],
            'total_score' => $takenExam->fresh()->total_points,
        ]);
    }

    /**
     * Finalize grading and mark the exam as graded
     */
    public function finalizeGrade(Request $request, $id)
    {
        $user = Auth::user();

        // Find the taken exam and verify ownership
        $takenExam = TakenExam::with(['exam.teachers', 'answers.item', 'exam.items'])->findOrFail($id);

        // Verify the authenticated teacher is assigned to this exam
        $isAssignedTeacher = $takenExam->exam->teachers()->where('teacher_id', $user->id)->exists();

        if (! $isAssignedTeacher) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if all manual grading items have been graded
        $ungradedItems = $takenExam->answers->filter(function ($answer) {
            return in_array($answer->item->type, ['essay', 'shortanswer']) &&
                   $answer->points_earned === null;
        });

        if ($ungradedItems->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Please grade all essay and short-answer questions before finalizing.',
                'ungraded_count' => $ungradedItems->count(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Grade auto-gradable questions (MCQ, True/False, Matching, Fill Blank, Short Answer)
            $this->gradeAutoGradableAnswers($takenExam);

            // Recalculate total score one final time
            $this->recalculateTotalScore($takenExam);

            // Mark as graded
            $takenExam->update([
                'status' => 'graded',
            ]);

            DB::commit();

            // TODO: Send notification to student if results are visible
            // if ($takenExam->exam->results_visible) {
            //     $this->notifyStudent($takenExam);
            // }

            return response()->json([
                'success' => true,
                'status' => 'graded',
                'final_score' => $takenExam->total_points,
                'message' => 'Grades finalized successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Grade finalization failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize grades. Please try again.',
            ], 500);
        }
    }

    /**
     * Grade all auto-gradable answers by comparing with correct answers
     */
    private function gradeAutoGradableAnswers(TakenExam $takenExam)
    {
        $autoGradableTypes = ['mcq', 'truefalse', 'matching', 'fillblank', 'fill_blank'];

        // Create lookup for exam items
        $itemsMap = $takenExam->exam->items->keyBy('id');

        foreach ($takenExam->answers as $answer) {
            // Only grade auto-gradable types that haven't been manually graded yet
            if (in_array($answer->item->type, $autoGradableTypes) && $answer->points_earned === null) {
                $correctAnswer = $this->getCorrectAnswer($answer->item);
                $isCorrect = $this->checkAnswer($answer->item, $answer->answer, $correctAnswer);

                // Assign full points if correct, 0 if incorrect, null if cannot be determined
                if ($isCorrect === true) {
                    $answer->points_earned = $answer->item->points;
                } elseif ($isCorrect === false) {
                    $answer->points_earned = 0;
                }

                $answer->save();
            }
        }
    }

    /**
     * Get correct answer for a single exam item
     */
    private function getCorrectAnswer($item)
    {
        switch ($item->type) {
            case 'mcq':
                $options = collect($item->options ?? []);
                $correctIndex = $options->search(function ($opt) {
                    return is_array($opt)
                        ? (! empty($opt['correct']))
                        : (! empty($opt->correct));
                });

                return $correctIndex !== false ? $correctIndex : null;

            case 'truefalse':
                return $this->normalizeBool($item->answer);

            case 'matching':
                return $item->pairs;

            case 'fillblank':
            case 'fill_blank':
                return $item->expected_answer;

            case 'shortanswer':
                return $item->expected_answer;

            case 'essay':
                return 'Manual grading required';

            default:
                return null;
        }
    }

    /**
     * Check if student answer is correct
     */
    private function checkAnswer($item, $studentAnswer, $correctAnswer)
    {
        if ($correctAnswer === null || $correctAnswer === 'Manual grading required') {
            return null; // Cannot auto-check
        }

        switch ($item->type) {
            case 'mcq':
                return (int) $studentAnswer === (int) $correctAnswer;

            case 'truefalse':
                // Normalize both sides; returns null if undetermined
                $expectedBool = $this->normalizeBool($correctAnswer);
                $studentBool = $this->normalizeBool($studentAnswer);

                if ($expectedBool === null || $studentBool === null) {
                    return null;
                }

                return $expectedBool === $studentBool;

            case 'matching':
                // For matching, both student answer and correct answer are now normalized to {left, right} format
                if (! is_string($studentAnswer)) {
                    return false;
                }

                $studentPairs = json_decode($studentAnswer, true);
                if (! is_array($studentPairs) || ! is_array($correctAnswer)) {
                    return false;
                }

                // Check if all student pairs exist in correct pairs
                if (count($studentPairs) !== count($correctAnswer)) {
                    return false;
                }

                foreach ($studentPairs as $studentPair) {
                    $found = false;
                    foreach ($correctAnswer as $correctPair) {
                        if ($studentPair['left'] === $correctPair['left'] && $studentPair['right'] === $correctPair['right']) {
                            $found = true;
                            break;
                        }
                    }
                    if (! $found) {
                        return false;
                    }
                }

                return true;

            case 'fillblank':
            case 'fill_blank':
            case 'shortanswer':
                return strtolower(trim((string) $studentAnswer)) === strtolower(trim((string) $correctAnswer));

            case 'essay':
                return null; // Manual grading

            default:
                return null;
        }
    }

    /**
     * Normalize boolean values for consistent comparison
     */
    private function normalizeBool($value): ?bool
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'True') {
            return true;
        } elseif ($value === false || $value === 0 || $value === '0' || $value === 'false' || $value === 'False') {
            return false;
        }

        return null;
    }

    /**
     * Recalculate the total score for a taken exam
     */
    private function recalculateTotalScore(TakenExam $takenExam)
    {
        $takenExam->load('answers');

        $totalScore = 0;

        foreach ($takenExam->answers as $answer) {
            $totalScore += $answer->points_earned ?? 0;
        }

        $takenExam->update(['total_points' => $totalScore]);
    }

    /**
     * Get list of all submissions that need grading (JSON API)
     */
    public function index()
    {
        $user = Auth::user();

        // OPTIMIZED: Eager load relationships and use more efficient filtering
        $pendingGrading = TakenExam::with([
            'exam:id,title,total_points,status',
            'user:id,name,email,year,section',
            'answers' => function ($query) {
                $query->whereNull('points_earned')
                    ->whereHas('item', function ($q) {
                        $q->whereIn('type', ['essay', 'shortanswer']);
                    })
                    ->with('item:id,exam_id,type,question,points');
            },
        ])
            ->whereHas('exam.teachers', function ($query) use ($user) {
                $query->where('teacher_id', $user->id);
            })
            ->where('status', 'submitted')
            ->whereHas('answers', function ($query) {
                $query->whereHas('item', function ($q) {
                    $q->whereIn('type', ['essay', 'shortanswer']);
                })->whereNull('points_earned');
            })
            ->orderBy('submitted_at', 'asc')
            ->get();

        return response()->json([
            'data' => $pendingGrading,
        ]);
    }
}
