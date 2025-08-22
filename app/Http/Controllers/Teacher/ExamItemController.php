<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamItem;
use Illuminate\Http\Request;

class ExamItemController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:mcq,truefalse,essay',
            'question' => 'required|string',
            'points' => 'required|integer|min:1',

            'expected_answer' => 'nullable|string',  // essay
            'answer' => 'nullable|boolean',          // true/false
            'options' => 'nullable|array',           // mcq
            'options.*.text' => 'required_with:options|string',
            'options.*.correct' => 'required_with:options|boolean',
        ]);

        $item = $exam->items()->create([
            'type' => $validated['type'],
            'question' => $validated['question'],
            'points' => $validated['points'],
            'expected_answer' => $validated['expected_answer'] ?? null,
            'answer' => $validated['answer'] ?? null,
            'options' => $validated['options'] ?? null,
        ]);

        return response()->json([
            'message' => 'Item added successfully',
            'item' => $item
        ], 201);
    }

    public function update(Request $request, Exam $exam, ExamItem $item)
    {
        // make sure the item belongs to the exam
        if ($item->exam_id !== $exam->id) {
            return response()->json(['error' => 'This item does not belong to the given exam.'], 403);
        }

        $validated = $request->validate([
            'question' => 'sometimes|required|string',
            'points' => 'sometimes|required|integer|min:1',
            'expected_answer' => 'nullable|string',
            'answer' => 'nullable|boolean',
            'options' => 'nullable|array',
            'options.*.text' => 'required_with:options|string',
            'options.*.correct' => 'required_with:options|boolean',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Item updated successfully',
            'item' => $item
        ]);
    }
}
