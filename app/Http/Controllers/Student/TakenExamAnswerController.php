<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\TakenExamAnswers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TakenExamAnswerController extends Controller
{

    public function store(Request $request, $takenExamId)
    {
        // Normalize legacy type value
        $type = $request->input('type');
        if ($type === 'fillblank') {
            $type = 'fill_blank';
            $request->merge(['type' => $type]);
        }

        // Base validation
        $rules = [
            'exam_item_id' => 'required|exists:exam_items,id',
            'type' => 'required|in:mcq,truefalse,fill_blank,shortanswer,essay,matching',
        ];

        // Type-specific answer validation
        if ($type === 'matching') {
            // If frontend sends JSON string, decode to array before validation
            $raw = $request->input('answer');
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge(['answer' => $decoded]);
                }
            }
            // Expect an array of indexes/nulls for each left-side pair
            $rules['answer'] = 'nullable|array';
            $rules['answer.*'] = 'nullable|integer';
        } else {
            // Allow scalar answers (nullable)
            $rules['answer'] = 'nullable';
        }

        $validated = $request->validate($rules);

        $answer = TakenExamAnswers::updateOrCreate(
            [
                'taken_exam_id' => $takenExamId,
                'exam_item_id' => $validated['exam_item_id'],
            ],
            [
                'type' => $validated['type'],
                'answer' => $validated['answer'] ?? null,
            ]
        );

        return response()->json($answer, 201);
    }

    public function show($takenExamId)
    {
        $answers = TakenExamAnswers::with('item')
            ->where('taken_exam_id', $takenExamId)
            ->get();

        return response()->json(['data' => $answers]);
    }

}
