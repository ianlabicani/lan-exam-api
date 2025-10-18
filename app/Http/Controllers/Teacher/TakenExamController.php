<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamActivityLog;
use App\Models\TakenExam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TakenExamController extends Controller
{
    /**
     * Display a listing of taken exams for a specific exam.
     */
    public function index($examId)
    {
        // Verify the exam belongs to the authenticated teacher and eager load items
        $exam = Exam::with('items')
            ->whereHas('teachers', function ($query) {
                $query->where('teacher_id', Auth::id());
            })->findOrFail($examId);

        // OPTIMIZED: Eager load all relationships upfront
        $takenExams = TakenExam::with(['user', 'answers.item'])
            ->where('exam_id', $exam->id)
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(function ($takenExam) use ($exam) {
                // Compare answers - use already loaded exam.items
                $takenExam->answer_comparison = $this->compareAnswers($exam->items, $takenExam->answers);

                return $takenExam;
            });

        // Calculate analytics
        $analytics = $this->calculateAnalytics($exam, $takenExams);

        return response()->json(
            [
                'data' => [
                    'exam' => $exam,
                    'takenExams' => $takenExams,
                    'analytics' => $analytics,
                ],
            ]
        );
    }

    /**
     * Calculate comprehensive analytics for the exam
     */
    private function calculateAnalytics($exam, $takenExams)
    {
        $submittedExams = $takenExams->filter(function ($takenExam) {
            return $takenExam->submitted_at !== null;
        });

        // Top Performers (Top 5)
        $topPerformers = $submittedExams
            ->sortByDesc('total_points')
            ->take(5)
            ->map(function ($takenExam) use ($exam) {
                $percentage = $exam->total_points > 0
                    ? round(($takenExam->total_points / $exam->total_points) * 100, 1)
                    : 0;

                return [
                    'name' => $takenExam->user->name,
                    'score' => $takenExam->total_points,
                    'percentage' => $percentage,
                    'submitted_at' => $takenExam->submitted_at,
                ];
            })
            ->values();

        // Question Analytics - Most Difficult Questions (lowest success rate)
        $questionStats = $exam->items->map(function ($item) use ($submittedExams) {
            $answers = $submittedExams->flatMap->answers->where('exam_item_id', $item->id);
            $totalAnswered = $answers->count();

            if ($totalAnswered === 0) {
                return null;
            }

            // Count correct answers
            $correctCount = 0;
            foreach ($answers as $answer) {
                if ($item->type === 'essay' || $item->type === 'shortanswer') {
                    // For manual grading, consider full points as correct
                    if ($answer->points_earned === $item->points) {
                        $correctCount++;
                    }
                } else {
                    // For auto-graded, check if they got full points
                    if ($answer->points_earned === $item->points) {
                        $correctCount++;
                    }
                }
            }

            $successRate = $totalAnswered > 0 ? round(($correctCount / $totalAnswered) * 100, 1) : 0;
            $unansweredCount = $submittedExams->count() - $totalAnswered;

            return [
                'id' => $item->id,
                'question' => strlen($item->question) > 80
                    ? substr($item->question, 0, 77).'...'
                    : $item->question,
                'full_question' => $item->question,
                'type' => $item->type,
                'level' => $item->level,
                'points' => $item->points,
                'total_answered' => $totalAnswered,
                'correct_count' => $correctCount,
                'unanswered_count' => $unansweredCount,
                'success_rate' => $successRate,
                'average_points' => $answers->avg('points_earned'),
            ];
        })->filter()->values();

        // Most Difficult Questions (lowest success rate, at least 3 answers)
        $mostDifficult = $questionStats
            ->filter(function ($stat) {
                return $stat['total_answered'] >= 3; // Only questions with at least 3 answers
            })
            ->sortBy('success_rate')
            ->take(5)
            ->values();

        // Most Unanswered Questions
        $mostUnanswered = $questionStats
            ->sortByDesc('unanswered_count')
            ->take(5)
            ->values();

        // Easiest Questions (highest success rate, at least 3 answers)
        $easiest = $questionStats
            ->filter(function ($stat) {
                return $stat['total_answered'] >= 3;
            })
            ->sortByDesc('success_rate')
            ->take(5)
            ->values();

        // Score Distribution
        $scoreRanges = [
            '90-100' => 0,
            '80-89' => 0,
            '70-79' => 0,
            '60-69' => 0,
            'Below 60' => 0,
        ];

        foreach ($submittedExams as $takenExam) {
            $percentage = $exam->total_points > 0
                ? ($takenExam->total_points / $exam->total_points) * 100
                : 0;

            if ($percentage >= 90) {
                $scoreRanges['90-100']++;
            } elseif ($percentage >= 80) {
                $scoreRanges['80-89']++;
            } elseif ($percentage >= 70) {
                $scoreRanges['70-79']++;
            } elseif ($percentage >= 60) {
                $scoreRanges['60-69']++;
            } else {
                $scoreRanges['Below 60']++;
            }
        }

        // Overall Statistics
        $averageScore = $submittedExams->avg('total_points');
        $averagePercentage = $exam->total_points > 0 && $submittedExams->count() > 0
            ? round(($averageScore / $exam->total_points) * 100, 1)
            : 0;

        $highestScore = $submittedExams->max('total_points');
        $lowestScore = $submittedExams->min('total_points');

        $passRate = $submittedExams->filter(function ($takenExam) use ($exam) {
            $percentage = $exam->total_points > 0
                ? ($takenExam->total_points / $exam->total_points) * 100
                : 0;

            return $percentage >= 75; // Consider 75% as passing
        })->count();

        $passPercentage = $submittedExams->count() > 0
            ? round(($passRate / $submittedExams->count()) * 100, 1)
            : 0;

        return [
            'top_performers' => $topPerformers,
            'most_difficult' => $mostDifficult,
            'most_unanswered' => $mostUnanswered,
            'easiest_questions' => $easiest,
            'score_distribution' => $scoreRanges,
            'average_score' => round($averageScore, 2),
            'average_percentage' => $averagePercentage,
            'highest_score' => $highestScore,
            'lowest_score' => $lowestScore,
            'pass_rate' => $passRate,
            'pass_percentage' => $passPercentage,
            'total_submitted' => $submittedExams->count(),
            'question_stats' => $questionStats,
        ];
    }

    /**
     * Normalize common truthy/falsy representations to boolean or null when undetermined.
     */
    private function normalizeBool($val): ?bool
    {
        if ($val === null) {
            return null;
        }

        if (is_bool($val)) {
            return $val;
        }

        if (is_numeric($val)) {
            return (int) $val === 1;
        }

        $s = strtolower(trim((string) $val));
        if (in_array($s, ['true', '1', 'yes', 'y', 't'], true)) {
            return true;
        }
        if (in_array($s, ['false', '0', 'no', 'n', 'f'], true)) {
            return false;
        }

        // Undetermined
        return null;
    }

    /**
     * Display details of a specific taken exam (JSON API).
     */
    public function show($examId, $takenExamId)
    {
        // OPTIMIZED: Verify exam and eager load items in single query
        $exam = Exam::with('items')
            ->whereHas('teachers', function ($query) {
                $query->where('teacher_id', Auth::id());
            })->findOrFail($examId);

        // OPTIMIZED: Eager load only necessary relationships
        $takenExam = TakenExam::with(['user', 'answers.item'])
            ->where('exam_id', $exam->id)
            ->findOrFail($takenExamId);

        // Load activity logs for this taken exam
        $activityLogs = ExamActivityLog::where('taken_exam_id', $takenExam->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Compare exam items with student answers - use already loaded exam.items
        $comparison = $this->compareAnswers($exam->items, $takenExam->answers);

        return response()->json([
            'exam' => $exam,
            'takenExam' => $takenExam,
            'comparison' => $comparison,
            'activityLogs' => $activityLogs,
        ]);
    }

    /**
     * Compare exam items with student answers
     */
    private function compareAnswers($examItems, $studentAnswers)
    {
        // Create a lookup for student answers by exam_item_id
        $answerLookup = $studentAnswers->keyBy('exam_item_id');

        return $examItems->map(function ($item) use ($answerLookup) {
            $studentAnswer = $answerLookup->get($item->id);
            $correctAnswer = $this->getCorrectAnswer($item);

            $isCorrect = null;
            $studentResponse = null;
            // Keep points_earned null if not graded yet
            $pointsEarned = $studentAnswer && $studentAnswer->points_earned !== null
                ? $studentAnswer->points_earned
                : null;

            if ($studentAnswer) {
                $studentResponse = $studentAnswer->answer;
                $isCorrect = $this->checkAnswer($item, $studentAnswer->answer, $correctAnswer);
            }

            return [
                'exam_item_id' => $item->id,
                'type' => $item->type,
                'question' => $item->question,
                'points' => $item->points,
                'points_earned' => $pointsEarned,
                'correct_answer' => $correctAnswer,
                'student_answer' => $studentResponse,
                'is_correct' => $isCorrect,
                'answered' => $studentAnswer !== null,
                'options' => $item->options ?? null,
                'pairs' => $item->pairs ?? null,
                'expected_answer' => $item->expected_answer ?? null,
            ];
        });
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
                // Normalize the stored 'answer' value to boolean/null
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
     * Update points for a manually graded answer (JSON API).
     */
    public function updatePoints(Request $request, $examId, $takenExamId, $answerId)
    {
        // Verify the exam belongs to the authenticated teacher
        $exam = Exam::whereHas('teachers', function ($query) {
            $query->where('teacher_id', Auth::id());
        })->findOrFail($examId);

        $takenExam = TakenExam::where('exam_id', $exam->id)->findOrFail($takenExamId);

        $answer = $takenExam->answers()->findOrFail($answerId);

        $validated = $request->validate([
            'points_earned' => 'required|integer|min:0|max:'.$answer->item->points,
        ]);

        $answer->update($validated);

        // Recalculate total points for the taken exam
        $totalPoints = $takenExam->answers()->sum('points_earned');
        $takenExam->update(['total_points' => $totalPoints]);

        return response()->json([
            'success' => true,
            'message' => 'Points updated successfully!',
            'updated_answer' => $answer,
            'total_points' => $takenExam->total_points,
        ]);
    }
}
