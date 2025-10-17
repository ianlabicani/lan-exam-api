<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamController extends Controller
{
    /**
     * Display a listing of exams with search and filters.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = $user->exams()
            ->with('items') // Eager load items to get count
            ->select([
                'exams.id',
                'exams.title',
                'exams.description',
                'exams.starts_at',
                'exams.ends_at',
                'exams.year',
                'exams.sections',
                'exams.status',
                'exams.total_points',
                'exams.created_at',
                'exams.updated_at',
            ]);

        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Status filter
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Year filter
        if ($request->filled('year')) {
            $year = $request->input('year');
            $query->whereRaw('JSON_CONTAINS(year, ?)', [$year]);
        }

        // Section filter
        if ($request->filled('section')) {
            $section = $request->input('section');
            $query->whereRaw('JSON_CONTAINS(sections, ?)', [json_encode($section)]);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('starts_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('starts_at', '<=', $request->input('date_to'));
        }

        // Pagination
        $perPage = $request->input('per_page', 10);
        $paginated = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends($request->query());

        $exams = $paginated->items();
        foreach ($exams as $exam) {
            $exam->makeHidden(['pivot']);
        }

        return response()->json([
            'data' => $exams,
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created exam in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'year' => 'required|array',
            'year.*' => 'required|in:1,2,3,4',
            'sections' => 'required|array',
            'sections.*' => 'required|in:a,b,c,d,e,f,g',
            'total_points' => 'integer|min:0',
            'tos' => 'required|array',
        ]);

        // Normalize years
        $years = array_values(array_filter($validated['year'], static fn ($v) => $v !== '' && $v !== null));

        if (empty($years)) {
            return back()->withErrors(['year' => 'At least one year is required.'])->withInput();
        }

        // Normalize sections
        $sections = array_values(array_filter($validated['sections'], static fn ($v) => $v !== '' && $v !== null));

        if (empty($sections)) {
            return back()->withErrors(['sections' => 'At least one section is required.'])->withInput();
        }

        $payload = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'year' => $years,
            'sections' => $sections,
            'status' => 'draft', // Always create as draft
            'total_points' => $validated['total_points'] ?? 0,
            'tos' => $validated['tos'],
        ];

        // Create exam (via relation to auto-attach teacher)
        $exam = $request->user()->exams()->create($payload);

        // Redirect to the exam show page to add items
        return redirect()->route('teacher.exams.show', $exam->id)
            ->with('success', 'Exam created successfully! Now you can add questions to your exam.');
    }

    /**
     * Display the specified exam.
     */
    public function show($id)
    {
        // OPTIMIZED: Single query with all necessary relationships eager loaded
        $exam = Exam::with([
            'items' => function ($query) {
                $query->orderBy('id', 'asc');
            },
            'takenExams' => function ($query) {
                $query->with(['user', 'answers'])
                    ->orderBy('submitted_at', 'desc');
            },
        ])
            ->whereHas('teachers', function ($query) {
                $query->where('teacher_id', Auth::id());
            })
            ->findOrFail($id);

        $examItems = $exam->items;

        // Use already loaded takenExams relationship (no additional query)
        $takers = $exam->takenExams->map(function ($takenExam) use ($exam) {
            // Calculate percentage
            $percentage = $exam->total_points > 0
                ? round(($takenExam->total_points / $exam->total_points) * 100, 2)
                : 0;

            // Count answered questions (already loaded via eager loading)
            $answeredCount = $takenExam->answers->count();
            $totalQuestions = $exam->items->count();

            return [
                'id' => $takenExam->id,
                'user' => $takenExam->user,
                'started_at' => $takenExam->started_at,
                'submitted_at' => $takenExam->submitted_at,
                'total_points' => $takenExam->total_points,
                'percentage' => $percentage,
                'status' => $takenExam->status,
                'answered_count' => $answeredCount,
                'total_questions' => $totalQuestions,
                'duration' => $takenExam->started_at && $takenExam->submitted_at
                    ? $takenExam->started_at->diffInMinutes($takenExam->submitted_at)
                    : null,
            ];
        });

        // Calculate statistics
        $totalTakers = $takers->count();
        $completedCount = $takers->where('submitted_at', '!=', null)->count();
        $gradedCount = $takers->where('status', 'graded')->count();
        $pendingGradingCount = $takers->where('status', 'submitted')->count();
        $averageScore = $completedCount > 0
            ? round($takers->where('submitted_at', '!=', null)->avg('total_points'), 2)
            : 0;

        return view('teacher.exams.show', compact(
            'exam',
            'examItems',
            'takers',
            'totalTakers',
            'completedCount',
            'gradedCount',
            'pendingGradingCount',
            'averageScore'
        ));
    }

    /**
     * Show the form for editing the specified exam.
     */
    public function edit($id)
    {
        $exam = Exam::with(['items'])
            ->whereHas('teachers', function ($query) {
                $query->where('teacher_id', Auth::id());
            })
            ->findOrFail($id);

        $exam->makeHidden(['pivot']);

        return response()->json([
            'exam' => $exam,
        ]);
    }

    /**
     * Update the specified exam in storage.
     */
    public function update(Request $request, string $id)
    {
        $exam = Exam::whereHas('teachers', function ($query) {
            $query->where('teacher_id', Auth::id());
        })->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
            'year' => 'sometimes|array',
            'year.*' => 'required|in:1,2,3,4',
            'sections' => 'sometimes|array',
            'sections.*' => 'required|in:a,b,c,d,e,f,g',
            'status' => 'sometimes|in:draft,ready,published,ongoing,closed,graded,archived',
            'total_points' => 'sometimes|integer|min:0',
            'tos' => 'sometimes|array',
        ]);

        $payload = $validated;

        // Normalize years when provided
        if ($request->has('year')) {
            $years = array_values(array_filter($validated['year'], static fn ($v) => $v !== '' && $v !== null));

            if (empty($years)) {
                return back()->withErrors(['year' => 'At least one year is required.'])->withInput();
            }

            $payload['year'] = $years;
        }

        // Normalize sections when provided
        if ($request->has('sections')) {
            $sections = array_values(array_filter($validated['sections'], static fn ($v) => $v !== '' && $v !== null));

            if (empty($sections)) {
                return back()->withErrors(['sections' => 'At least one section is required.'])->withInput();
            }

            $payload['sections'] = $sections;
        }

        $exam->update($payload);

        return redirect()->route('teacher.exams.show', $exam->id)
            ->with('success', 'Exam updated successfully!');
    }

    /**
     * Update exam status.
     */
    public function updateStatus(Request $request, $id)
    {
        $exam = Exam::whereHas('teachers', function ($query) {
            $query->where('teacher_id', Auth::id());
        })->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:draft,ready,published,ongoing,closed,graded,archived',
        ]);

        // Use the transition method to ensure valid state changes
        if ($exam->transitionTo($validated['status'])) {
            return redirect()->route('teacher.exams.show', $exam->id)
                ->with('success', 'Exam status updated successfully!');
        }

        return redirect()->route('teacher.exams.show', $exam->id)
            ->with('error', 'Invalid status transition. Please follow the exam lifecycle.');
    }

    /**
     * Remove the specified exam from storage.
     */
    public function destroy(string $id)
    {
        $exam = Exam::whereHas('teachers', function ($query) {
            $query->where('teacher_id', Auth::id());
        })->findOrFail($id);

        $exam->delete();

        return redirect()->route('teacher.exams.index')
            ->with('success', 'Exam deleted successfully!');
    }

    /**
     * Get exam takers
     */
    public function getExamTakers(int $id)
    {
        $exam = Exam::whereHas('teachers', function ($query) {
            $query->where('teacher_id', Auth::id());
        })->findOrFail($id);

        $takers = $exam->takenExams()->with('user')->get()->pluck('user')->filter();

        return view('teacher.exams.partials.takers', compact('exam', 'takers'));
    }
}
