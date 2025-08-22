<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    // List all exams
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $exams = $user->exams ?? [];
        return response()->json(["exams" => $exams]);
    }

    public function show(string $id)
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

        $exam = $request->user()->exams()->create($validated);

        return response()->json($exam, 201);
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

    // Delete exam
    public function destroy(string $id)
    {
        $exam = Exam::findOrFail($id);
        $exam->delete();

        return response()->json(null, 204);
    }
}
