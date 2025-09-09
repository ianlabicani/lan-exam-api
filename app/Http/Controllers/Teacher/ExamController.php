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

    public function show(int $id)
    {
        $exam = Exam::findOrFail($id);
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
            'section' => 'required|string',
            'status' => 'in:draft,active,archived',
            'total_points' => 'integer|min:0',
        ]);

        // Create exam
        $exam = $request->user()->exams()->create($validated);


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
            'section' => 'sometimes|string',
            'status' => 'in:draft,active,archived',
            'total_points' => 'integer|min:0',
        ]);

        $exam->update($validated);

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
