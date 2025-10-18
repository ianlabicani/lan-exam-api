<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\TakenExam;
use App\Models\ExamItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Display analytics dashboard
     */
    public function index()
    {
        $user = Auth::user();

        // Get teacher's exams with optimized queries
        $exams = $user->exams()
            ->with(['items', 'takenExams.user'])
            ->select([
                'exams.id',
                'exams.title',
                'exams.status',
                'exams.total_points',
                'exams.created_at',
                'exams.starts_at',
                'exams.ends_at',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Overall Statistics
        $totalExams = $exams->count();
        $publishedExams = $exams->where('status', 'published')->count();
        $ongoingExams = $exams->where('status', 'ongoing')->count();
        $closedExams = $exams->where('status', 'closed')->count();

        // Student Statistics - OPTIMIZED: Use single query with distinct count
        $takenExamsBaseQuery = TakenExam::whereHas('exam.teachers', function ($query) use ($user) {
            $query->where('teacher_id', $user->id);
        });

        $totalTakenExams = $takenExamsBaseQuery->count();
        $totalSubmissions = (clone $takenExamsBaseQuery)->whereNotNull('submitted_at')->count();
        $totalStudents = (clone $takenExamsBaseQuery)->distinct('user_id')->count('user_id');
        $pendingGradingCount = (clone $takenExamsBaseQuery)->where('status', 'submitted')->count();

        // Performance Over Time (last 6 exams)
        $recentExamPerformance = $exams->take(6)->map(function ($exam) {
            $takenExams = $exam->takenExams->where('submitted_at', '!=', null);
            $averageScore = $takenExams->avg('total_points');
            $totalPossible = $exam->total_points;
            $percentage = $totalPossible > 0 ? round(($averageScore / $totalPossible) * 100, 2) : 0;

            return [
                'title' => $exam->title,
                'short_title' => strlen($exam->title) > 30 ? substr($exam->title, 0, 27) . '...' : $exam->title,
                'average_percentage' => $percentage,
                'submissions' => $takenExams->count(),
                'total_points' => $totalPossible,
            ];
        });

        // Question Type Distribution
        $questionTypeDistribution = ExamItem::whereHas('exam.teachers', function ($query) use ($user) {
            $query->where('teacher_id', $user->id);
        })
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->type => $item->count];
            });

        // Difficulty Distribution
        $difficultyDistribution = ExamItem::whereHas('exam.teachers', function ($query) use ($user) {
            $query->where('teacher_id', $user->id);
        })
            ->select('level', DB::raw('count(*) as count'))
            ->groupBy('level')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->level ?? 'unknown' => $item->count];
            });

        // Top Performing Exams (by average score)
        $topPerformingExams = $exams->filter(function ($exam) {
            return $exam->takenExams->where('submitted_at', '!=', null)->count() > 0;
        })->map(function ($exam) {
            $takenExams = $exam->takenExams->where('submitted_at', '!=', null);
            $averageScore = $takenExams->avg('total_points');
            $percentage = $exam->total_points > 0
                ? round(($averageScore / $exam->total_points) * 100, 2)
                : 0;

            return [
                'id' => $exam->id,
                'title' => $exam->title,
                'average_percentage' => $percentage,
                'submissions' => $takenExams->count(),
                'status' => $exam->status,
            ];
        })->sortByDesc('average_percentage')->take(5)->values();

        // Recent Activity (last 10 submissions)
        $recentActivity = TakenExam::with(['exam', 'user'])
            ->whereHas('exam.teachers', function ($query) use ($user) {
                $query->where('teacher_id', $user->id);
            })
            ->whereNotNull('submitted_at')
            ->orderBy('submitted_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($takenExam) {
                $percentage = $takenExam->exam->total_points > 0
                    ? round(($takenExam->total_points / $takenExam->exam->total_points) * 100, 2)
                    : 0;

                return [
                    'id' => $takenExam->id,
                    'student_name' => $takenExam->user->name,
                    'exam_title' => $takenExam->exam->title,
                    'score' => $takenExam->total_points,
                    'total' => $takenExam->exam->total_points,
                    'percentage' => $percentage,
                    'status' => $takenExam->status,
                    'submitted_at' => $takenExam->submitted_at,
                ];
            });

        return response()->json(['data' => [
            'totalExams' => $totalExams,
            'publishedExams' => $publishedExams,
            'ongoingExams' => $ongoingExams,
            'closedExams' => $closedExams,
            'totalTakenExams' => $totalTakenExams,
            'totalSubmissions' => $totalSubmissions,
            'totalStudents' => $totalStudents,
            'pendingGradingCount' => $pendingGradingCount,
            'recentExamPerformance' => $recentExamPerformance,
            'questionTypeDistribution' => $questionTypeDistribution,
            'difficultyDistribution' => $difficultyDistribution,
            'topPerformingExams' => $topPerformingExams,
            'recentActivity' => $recentActivity
        ]]);
    }

    /**
     * Get detailed analytics for a specific exam
     */
    public function examDetails($id)
    {
        $user = Auth::user();

        // OPTIMIZED: Eager load all necessary relationships upfront
        $exam = Exam::with([
                'items',
                'takenExams' => function ($query) {
                    $query->whereNotNull('submitted_at')
                        ->with(['user:id,name,email', 'answers:id,taken_exam_id,exam_item_id,points_earned']);
                }
            ])
            ->whereHas('teachers', function ($query) use ($user) {
                $query->where('teacher_id', $user->id);
            })
            ->findOrFail($id);

        $takenExams = $exam->takenExams;

        // Calculate statistics
        $totalSubmissions = $takenExams->count();
        $averageScore = $takenExams->avg('total_points');
        $highestScore = $takenExams->max('total_points');
        $lowestScore = $takenExams->min('total_points');
        $averagePercentage = $exam->total_points > 0
            ? round(($averageScore / $exam->total_points) * 100, 2)
            : 0;

        // Score distribution
        $scoreDistribution = $takenExams->groupBy(function ($takenExam) use ($exam) {
            $percentage = $exam->total_points > 0
                ? ($takenExam->total_points / $exam->total_points) * 100
                : 0;

            if ($percentage >= 90) return '90-100%';
            if ($percentage >= 80) return '80-89%';
            if ($percentage >= 70) return '70-79%';
            if ($percentage >= 60) return '60-69%';
            return 'Below 60%';
        })->map->count();

        // Question analysis
        $questionAnalysis = $exam->items->map(function ($item) use ($takenExams) {
            $answers = $takenExams->flatMap->answers->where('exam_item_id', $item->id);
            $totalAnswers = $answers->count();

            if ($totalAnswers === 0) {
                return [
                    'id' => $item->id,
                    'question' => $item->question,
                    'type' => $item->type,
                    'total_answers' => 0,
                    'correct_count' => 0,
                    'success_rate' => 0,
                    'average_points' => 0,
                ];
            }

            $correctCount = $answers->where('points_earned', $item->points)->count();
            $successRate = round(($correctCount / $totalAnswers) * 100, 2);
            $averagePoints = round($answers->avg('points_earned'), 2);

            return [
                'id' => $item->id,
                'question' => $item->question,
                'type' => $item->type,
                'level' => $item->level,
                'total_answers' => $totalAnswers,
                'correct_count' => $correctCount,
                'success_rate' => $successRate,
                'average_points' => $averagePoints,
                'max_points' => $item->points,
            ];
        });

        return response()->json([
            'data' => [
                'exam' => $exam,
                'totalSubmissions' => $totalSubmissions,
                'averageScore' => $averageScore,
                'highestScore' => $highestScore,
                'lowestScore' => $lowestScore,
                'averagePercentage' => $averagePercentage,
                'scoreDistribution' => $scoreDistribution,
                'questionAnalysis' => $questionAnalysis,
            ],
        ]);
    }
}
