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

    public function show(int $id)
    {
        $exam = Exam::findOrFail($id);
        $exam = $exam->load('items');
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

            // items
            'items' => 'array',
            'items.*.type' => 'required|string|in:mcq,truefalse,essay',
            'items.*.question' => 'required|string',
            'items.*.points' => 'required|integer|min:1',

            // optional fields depending on type
            'items.*.expected_answer' => 'nullable|string',   // for essay
            'items.*.answer' => 'nullable|boolean',          // for true/false
            'items.*.options' => 'nullable|array',           // for mcq
            'items.*.options.*.text' => 'required_with:items.*.options|string',
            'items.*.options.*.correct' => 'required_with:items.*.options|boolean',
        ]);

        // Create exam
        $exam = $request->user()->exams()->create($validated);

        // Attach items if provided
        if (!empty($validated['items'])) {
            foreach ($validated['items'] as $item) {
                $exam->items()->create([
                    'type' => $item['type'],
                    'question' => $item['question'],
                    'points' => $item['points'],
                    'expected_answer' => $item['expected_answer'] ?? null,
                    'answer' => $item['answer'] ?? null,
                    'options' => $item['options'] ?? null,
                ]);
            }
        }

        return response()->json($exam->load('items'), 201);
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

        return response()->json([
            'message' => 'Exam status updated successfully',
            'exam' => $exam
        ]);
    }

    // Delete exam
    public function destroy(string $id)
    {
        $exam = Exam::findOrFail($id);
        $exam->delete();

        return response()->json(null, 204);
    }
}
