<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $exams = $user->exams ?? [];
        return response()->json($exams);
    }

    public function show(Exam $exam)
    {
        return response()->json($exam);
    }

    // Create exam
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'year' => 'required|string',
            'sections' => 'required', // string like "E,F" or array
            'status' => 'in:draft,active,archived,published',
            'total_points' => 'integer|min:0',
            'tos' => 'required|array',
        ]);

        // Normalize sections (accepts comma-separated string or array)
        $sectionsInput = $request->input('sections');
        if (is_string($sectionsInput)) {
            $sections = array_values(array_filter(array_map(static function ($s) {
                return trim($s);
            }, explode(',', $sectionsInput)), static fn($v) => $v !== ''));
        } elseif (is_array($sectionsInput)) {
            $sections = array_values(array_filter(array_map(static function ($s) {
                return is_string($s) ? trim($s) : $s;
            }, $sectionsInput), static fn($v) => $v !== '' && $v !== null));
        } else {
            $sections = [];
        }

        if (empty($sections)) {
            return response()->json(['errors' => ['sections' => ['At least one section is required.']]], 422);
        }

        $payload = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'year' => $validated['year'],
            'sections' => $sections,
            'status' => $validated['status'] ?? 'draft',
            'total_points' => $validated['total_points'] ?? 0,
            'tos' => $validated['tos'],
        ];

        // Create exam (via relation to auto-attach teacher)
        $exam = $request->user()->exams()->create($payload);


        return response()->json(["exam" => $exam]);
    }


    // Update exam
    public function update(Request $request, string $id)
    {
        $exam = Exam::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
            'year' => 'sometimes|string',
            'sections' => 'sometimes', // string or array
            'status' => 'in:draft,active,archived,published',
            'total_points' => 'integer|min:0',
            'tos' => 'sometimes|array',
        ]);

        $payload = $validated;

        // Normalize sections when provided
        if ($request->has('sections')) {
            $sectionsInput = $request->input('sections');
            if (is_string($sectionsInput)) {
                $sections = array_values(array_filter(array_map(static function ($s) {
                    return trim($s);
                }, explode(',', $sectionsInput)), static fn($v) => $v !== ''));
            } elseif (is_array($sectionsInput)) {
                $sections = array_values(array_filter(array_map(static function ($s) {
                    return is_string($s) ? trim($s) : $s;
                }, $sectionsInput), static fn($v) => $v !== '' && $v !== null));
            } else {
                $sections = [];
            }

            if (empty($sections)) {
                return response()->json(['errors' => ['sections' => ['At least one section is required.']]], 422);
            }

            $payload['sections'] = $sections;
        }

        $exam->update($payload);

        return response()->json($exam);
    }


    public function updateStatus(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,active,archived,published',
        ]);

        $exam->update(['status' => $validated['status']]);

        return response()->json($exam);
    }

    // Delete exam
    public function destroy(string $id)
    {
        $exam = Exam::findOrFail($id);
        $exam->delete();

        return response()->json(null, 204);
    }

    public function getExamTakers(int $id)
    {
        $exam = Exam::findOrFail($id);
        $takers = $exam->takenExams()->with('user')->get()->pluck('user')->filter();
        return response()->json(['takers' => $takers->values()]);
    }
}
