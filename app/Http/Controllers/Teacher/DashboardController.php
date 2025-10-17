<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\TakenExam;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the teacher dashboard with real-time statistics
     */
    public function index()
    {
        $user = Auth::user();

        // OPTIMIZED: Single base query for statistics
        $examStatsQuery = $user->exams()
            ->select([
                'exams.id',
                'exams.title',
                'exams.status',
                'exams.total_points',
                'exams.starts_at',
                'exams.ends_at',
                'exams.created_at',
            ]);

        // Overall exam statistics
        $totalExams = (clone $examStatsQuery)->count();
        $activeExams = (clone $examStatsQuery)->whereIn('status', ['published', 'ongoing'])->count();
        $draftExams = (clone $examStatsQuery)->where('status', 'draft')->count();
        $completedExams = (clone $examStatsQuery)->whereIn('status', ['closed', 'archived'])->count();

        // Student and submission statistics - OPTIMIZED
        $takenExamsBaseQuery = TakenExam::whereHas('exam.teachers', function ($query) use ($user) {
            $query->where('teacher_id', $user->id);
        });

        $totalStudents = (clone $takenExamsBaseQuery)->distinct('user_id')->count('user_id');
        $totalSubmissions = (clone $takenExamsBaseQuery)->whereNotNull('submitted_at')->count();
        $pendingGradingCount = (clone $takenExamsBaseQuery)->where('status', 'submitted')->count();

        // Active takers (students currently taking exams)
        $activeTakersCount = (clone $takenExamsBaseQuery)
            ->whereNotNull('started_at')
            ->whereNull('submitted_at')
            ->whereHas('exam', function ($query) {
                $query->where('status', 'ongoing');
            })
            ->count();

        // Recent Exams - OPTIMIZED with eager loading
        $recentExams = $user->exams()
            ->with([
                'items:id,exam_id,points',
                'takenExams' => function ($query) {
                    $query->select(['id', 'exam_id', 'user_id', 'total_points', 'submitted_at', 'status'])
                        ->whereNotNull('submitted_at');
                }
            ])
            ->select([
                'exams.id',
                'exams.title',
                'exams.status',
                'exams.total_points',
                'exams.starts_at',
                'exams.ends_at',
                'exams.created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($exam) {
                $takenExams = $exam->takenExams;
                $completed = $takenExams->whereNotNull('submitted_at');

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'status' => $exam->status,
                    'schedule' => $exam->starts_at?->format('M d, Y g:i A') ?? 'Not scheduled',
                    'duration' => $exam->starts_at && $exam->ends_at
                        ? $exam->starts_at->diffInMinutes($exam->ends_at)
                        : 0,
                    'questions' => $exam->items->count(),
                    'takers' => $takenExams->count(),
                    'completed' => $completed->count(),
                    'pending' => $takenExams->count() - $completed->count(),
                    'average_score' => $exam->total_points > 0 && $completed->count() > 0
                        ? round(($completed->avg('total_points') / $exam->total_points) * 100, 1)
                        : 0,
                ];
            });

        // Active Takers - OPTIMIZED with targeted eager loading
        $activeTakers = TakenExam::with([
                'user:id,name,email',
                'exam:id,title,starts_at,ends_at'
            ])
            ->whereHas('exam.teachers', function ($query) use ($user) {
                $query->where('teacher_id', $user->id);
            })
            ->whereHas('exam', function ($query) {
                $query->where('status', 'ongoing');
            })
            ->whereNotNull('started_at')
            ->whereNull('submitted_at')
            ->withCount([
                'answers as answered_count'
            ])
            ->orderBy('started_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($takenExam) {
                $startedAt = $takenExam->started_at;
                $examEndsAt = $takenExam->exam->ends_at;
                $now = now();

                // Calculate time remaining
                $timeRemaining = 'Expired';
                if ($examEndsAt && $now < $examEndsAt) {
                    $diff = $now->diff($examEndsAt);
                    $timeRemaining = sprintf('%02d:%02d:%02d',
                        $diff->h + ($diff->days * 24),
                        $diff->i,
                        $diff->s
                    );
                }

                // Calculate progress (percentage of time elapsed)
                $progress = 0;
                if ($startedAt && $examEndsAt) {
                    $totalDuration = $startedAt->diffInMinutes($examEndsAt);
                    $elapsed = $startedAt->diffInMinutes($now);
                    $progress = $totalDuration > 0 ? min(100, round(($elapsed / $totalDuration) * 100)) : 0;
                }

                return [
                    'id' => $takenExam->id,
                    'student' => $takenExam->user->name,
                    'exam' => $takenExam->exam->title,
                    'started' => $startedAt->format('g:i A'),
                    'time_remaining' => $timeRemaining,
                    'progress' => $progress,
                    'answered_count' => $takenExam->answered_count,
                    'activity_flags' => 0, // TODO: Implement in Phase 2 - Security & Anti-Cheating
                ];
            });

        // Pending Grading - OPTIMIZED with filtered eager loading
        $pendingGrading = TakenExam::with([
                'user:id,name,email',
                'exam:id,title,total_points',
                'answers' => function ($query) {
                    $query->whereNull('points_earned')
                        ->whereHas('item', function ($q) {
                            $q->whereIn('type', ['essay', 'shortanswer']);
                        })
                        ->with('item:id,exam_id,type');
                }
            ])
            ->whereHas('exam.teachers', function ($query) use ($user) {
                $query->where('teacher_id', $user->id);
            })
            ->where('status', 'submitted')
            ->whereNotNull('submitted_at')
            ->orderBy('submitted_at', 'asc')
            ->take(10)
            ->get()
            ->map(function ($takenExam) {
                $ungradedAnswers = $takenExam->answers;
                $essayCount = $ungradedAnswers->where('item.type', 'essay')->count();
                $shortAnswerCount = $ungradedAnswers->where('item.type', 'shortanswer')->count();

                // Calculate auto score (already graded objective questions)
                $autoScore = $takenExam->exam->total_points > 0
                    ? round(($takenExam->total_points / $takenExam->exam->total_points) * 100, 1)
                    : 0;

                return [
                    'id' => $takenExam->id,
                    'student' => $takenExam->user->name,
                    'exam' => $takenExam->exam->title,
                    'exam_id' => $takenExam->exam->id,
                    'submitted' => $takenExam->submitted_at->diffForHumans(),
                    'essay_count' => $essayCount,
                    'short_answer_count' => $shortAnswerCount,
                    'auto_score' => $autoScore,
                ];
            });

        // Performance Trend (last 30 days) - OPTIMIZED with aggregation
        $performanceData = $user->exams()
            ->with([
                'takenExams' => function ($query) {
                    $query->select(['id', 'exam_id', 'total_points', 'submitted_at'])
                        ->whereNotNull('submitted_at')
                        ->where('submitted_at', '>=', now()->subDays(30));
                }
            ])
            ->select(['exams.id', 'exams.title', 'exams.total_points'])
            ->whereHas('takenExams', function ($query) {
                $query->whereNotNull('submitted_at')
                    ->where('submitted_at', '>=', now()->subDays(30));
            })
            ->orderBy('id', 'desc')
            ->take(6)
            ->get()
            ->map(function ($exam) {
                $submissions = $exam->takenExams;
                $avgScore = $submissions->avg('total_points');
                $percentage = $exam->total_points > 0
                    ? round(($avgScore / $exam->total_points) * 100, 1)
                    : 0;

                return [
                    'exam' => strlen($exam->title) > 25
                        ? substr($exam->title, 0, 22) . '...'
                        : $exam->title,
                    'average' => $percentage,
                ];
            });

        $stats = [
            'total_exams' => $totalExams,
            'active_exams' => $activeExams,
            'draft_exams' => $draftExams,
            'completed_exams' => $completedExams,
            'total_students' => $totalStudents,
            'total_submissions' => $totalSubmissions,
            'active_takers' => $activeTakersCount,
            'pending_grading' => $pendingGradingCount,
        ];

            return response()->json(['data' => [
                'stats' => $stats,
                'recentExams' => $recentExams,
                'activeTakers' => $activeTakers,
                'pendingGrading' => $pendingGrading,
                'performanceData' => $performanceData
            ]]);
    }
}
