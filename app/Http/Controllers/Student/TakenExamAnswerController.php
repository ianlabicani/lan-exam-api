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
        $validated = $request->validate([
            'exam_item_id' => 'required|exists:exam_items,id',
            'type' => 'required|in:mcq,truefalse,essay',
            'answer' => 'nullable',
        ]);

        $answer = TakenExamAnswers::updateOrCreate(
            [
                'taken_exam_id' => $takenExamId,
                'exam_item_id' => $validated['exam_item_id'],
            ],
            [
                'type' => $validated['type'],
                'answer' => $validated['answer'],
            ]
        );

        return response()->json($answer, 201);
    }

}
