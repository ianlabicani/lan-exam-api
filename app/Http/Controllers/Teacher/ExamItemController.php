<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamItem;
use Illuminate\Http\Request;

class ExamItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

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



    /**
     * Display the specified resource.
     */
    public function show(ExamItem $examItem)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ExamItem $examItem)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExamItem $examItem)
    {
        //
    }
}
