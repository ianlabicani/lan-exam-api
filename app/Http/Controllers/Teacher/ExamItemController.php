<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamItemController extends Controller
{
    public function index(Request $request)
    {
        $examId = $request->route('examId');

        // Verify the exam belongs to the authenticated teacher
        $exam = Exam::whereHas('teachers', function ($query) {
            $query->where('teacher_id', Auth::id());
        })->findOrFail($examId);

        $items = $exam->items()->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * Store a newly created exam item.
     */
    public function store(Request $request, $examId)
    {

        // Verify the exam belongs to the authenticated teacher
        $exam = Exam::whereHas('teachers', function ($query) {
            $query->where('teacher_id', Auth::id());
        })->findOrFail($examId);

        // Check if exam can be edited (only draft and ready status)
        if (! $exam->canBeEdited()) {
            return response()->json([
                'error' => 'Cannot add items to this exam. Only exams in Draft or Ready status can be edited.',
            ], 422);
        }

        $payload = $request->validate([
            'type' => 'required|string|in:mcq,truefalse,fillblank,shortanswer,essay,matching',
            'level' => 'required|string|in:easy,moderate,difficult',
            'question' => 'required|string',
            'points' => 'required|integer|min:1',
            'expected_answer' => 'nullable|string',
            'answer' => 'nullable|string',
            'options' => 'nullable|array',
            'options.*.text' => 'required_with:options|string',
            'options.*.correct' => 'nullable',
            'pairs' => 'nullable|array',
            'pairs.*.left' => 'required_with:pairs|string',
            'pairs.*.right' => 'required_with:pairs|string',
        ]);

        switch ($payload['type']) {
            case 'mcq':
                $payload = $this->prepareMcq($request, $payload);
                if (isset($payload['_error'])) {
                    return $payload['_error'];
                }
                break;
            case 'truefalse':
                $payload = $this->prepareTrueFalse($request, $payload);
                if (isset($payload['_error'])) {
                    return $payload['_error'];
                }
                break;
            case 'essay':
                $payload = $this->prepareEssay($payload);
                if (isset($payload['_error'])) {
                    return $payload['_error'];
                }
                break;
            case 'fillblank':
                $payload = $this->prepareFillBlank($payload);
                if (isset($payload['_error'])) {
                    return $payload['_error'];
                }
                break;
            case 'shortanswer':
                $payload = $this->prepareShortAnswer($payload);
                if (isset($payload['_error'])) {
                    return $payload['_error'];
                }
                break;
            case 'matching':
                $payload = $this->prepareMatching($request, $payload);
                if (isset($payload['_error'])) {
                    return $payload['_error'];
                }
                break;
        }

        $item = $exam->items()->create([
            'type' => $payload['type'],
            'level' => $payload['level'],
            'question' => $payload['question'],
            'points' => $payload['points'],
            'expected_answer' => $payload['expected_answer'] ?? null,
            'answer' => $payload['answer'] ?? null,
            'options' => $payload['options'] ?? null,
            'pairs' => $payload['pairs'] ?? null,
        ]);

        // Recalculate total points
        $total = $exam->items()->sum('points');
        if ($exam->total_points !== $total) {
            $exam->update(['total_points' => $total]);
        }

        return response()->json([
            'data' => $item,
        ]);
    }

    /**
     * Update the specified exam item.
     */
    public function update(Request $request, $examId, $itemId)
    {
        // Verify the exam belongs to the authenticated teacher
        $exam = Exam::whereHas('teachers', function ($query) {
            $query->where('teacher_id', Auth::id());
        })->findOrFail($examId);

        $examItem = ExamItem::where('exam_id', $examId)
            ->findOrFail($itemId);

        // Check if exam can be edited (only draft and ready status)
        if (! $exam->canBeEdited()) {
            return response()->json([
                'error' => 'Cannot modify items of this exam. Only exams in Draft or Ready status can be edited.',
            ], 422);
        }

        $payload = $request->validate([
            'type' => 'sometimes|string|in:mcq,truefalse,fillblank,shortanswer,essay,matching',
            'question' => 'sometimes|string',
            'points' => 'sometimes|integer|min:1',
            'expected_answer' => 'nullable|string',
            'answer' => 'nullable|string',
            'options' => 'nullable|array',
            'options.*.text' => 'required_with:options|string',
            'options.*.correct' => 'nullable',
            'pairs' => 'nullable|array',
            'pairs.*.left' => 'required_with:pairs|string',
            'pairs.*.right' => 'required_with:pairs|string',
        ]);

        // Merge current item data with incoming payload so helpers can operate on a full dataset
        $data = array_merge($examItem->toArray(), $payload);

        $type = $payload['type'] ?? $examItem->type;

        switch ($type) {
            case 'mcq':
                $data = $this->prepareMcq($request, $data);
                if (isset($data['_error'])) {
                    return $data['_error'];
                }
                break;
            case 'truefalse':
                $data = $this->prepareTrueFalse($request, $data);
                if (isset($data['_error'])) {
                    return $data['_error'];
                }
                break;
            case 'essay':
                $data = $this->prepareEssay($data);
                if (isset($data['_error'])) {
                    return $data['_error'];
                }
                break;
            case 'fillblank':
                $data = $this->prepareFillBlank($data);
                if (isset($data['_error'])) {
                    return $data['_error'];
                }
                break;
            case 'shortanswer':
                $data = $this->prepareShortAnswer($data);
                if (isset($data['_error'])) {
                    return $data['_error'];
                }
                break;
            case 'matching':
                $data = $this->prepareMatching($request, $data);
                if (isset($data['_error'])) {
                    return $data['_error'];
                }
                break;
        }

        $updateData = [
            'type' => $type,
            'level' => $examItem->level,
            'question' => $data['question'] ?? $examItem->question,
            'points' => $data['points'] ?? $examItem->points,
            'expected_answer' => $data['expected_answer'] ?? null,
            'answer' => $data['answer'] ?? null,
            'options' => $data['options'] ?? null,
            'pairs' => $data['pairs'] ?? null,
        ];

        // Update the exam item
        $examItem->update($updateData);

        // Recalculate total points
        $total = $exam->items()->sum('points');
        if ($exam->total_points !== $total) {
            $exam->update(['total_points' => $total]);
        }

        return response()->json([
            'data' => $examItem->fresh(),
        ]);
    }

    /**
     * Remove the specified exam item.
     */
    public function destroy(Request $request, $examId, $itemId)
    {
        $examItem = ExamItem::findOrFail($itemId);
        $exam = $examItem->exam;

        // Verify the exam ID matches
        if ($exam->id != $examId) {
            abort(404, 'Exam item not found');
        }

        // Verify the exam belongs to the authenticated teacher
        if (! $exam->teachers()->where('teacher_id', Auth::id())->exists()) {
            abort(403, 'Unauthorized');
        }

        // Check if exam can be edited (only draft and ready status)
        if (! $exam->canBeEdited()) {
            return response()->json([
                'error' => 'Cannot delete items from this exam. Only exams in Draft or Ready status can be edited.',
            ], 422);
        }

        $examItem->delete();

        // Recalculate total points
        $total = $exam->items()->sum('points');
        if ($exam->total_points !== $total) {
            $exam->update(['total_points' => $total]);
        }

        return response()->json([
            'message' => 'Question deleted successfully!',
            'data' => [
                'exam_id' => $exam->id,
                'total_points' => $total,
            ],
        ]);
    }

    // Preparation methods for each question type

    private function prepareMcq(Request $request, array $data): array
    {
        $options = $request->input('options', []);

        if (empty($options) || ! is_array($options)) {
            return ['_error' => response()->json([
                'error' => 'Options are required for multiple choice questions.',
            ], 422)];
        }

        // Normalize the correct field - convert "1" string to true boolean
        $normalizedOptions = [];
        foreach ($options as $option) {
            $normalizedOptions[] = [
                'text' => $option['text'] ?? '',
                'correct' => isset($option['correct']) && ($option['correct'] === '1' || $option['correct'] === 1 || $option['correct'] === true),
            ];
        }

        $hasCorrect = collect($normalizedOptions)->contains(fn ($o) => $o['correct'] === true);

        if (! $hasCorrect) {
            return ['_error' => response()->json([
                'error' => 'At least one option must be marked correct.',
            ], 422)];
        }

        $data['options'] = $normalizedOptions;
        unset($data['answer'], $data['pairs']);

        return $data;
    }

    private function prepareTrueFalse(Request $request, array $data): array
    {
        $value = $request->input('answer', $data['answer'] ?? null);

        if ($value === null) {
            return ['_error' => response()->json([
                'error' => 'Answer is required for true/false questions.',
            ], 422)];
        }

        if ($value === true) {
            $value = 'true';
        } elseif ($value === false) {
            $value = 'false';
        } elseif (! is_string($value)) {
            return ['_error' => response()->json([
                'error' => 'Answer must be the string "true" or "false".',
            ], 422)];
        }

        $normalized = strtolower(trim((string) $value));
        if (! in_array($normalized, ['true', 'false'], true)) {
            return ['_error' => response()->json([
                'error' => 'Answer must be the string "true" or "false".',
            ], 422)];
        }

        $data['answer'] = $normalized;
        $data['expected_answer'] = null;
        $data['options'] = null;
        $data['pairs'] = null;

        return $data;
    }

    private function prepareEssay(array $data): array
    {
        if (empty($data['expected_answer'])) {
            return ['_error' => response()->json([
                'error' => 'Expected answer (reference) is required for essay questions.',
            ], 422)];
        }
        unset($data['answer'], $data['options']);

        return $data;
    }

    private function prepareFillBlank(array $data): array
    {
        if (empty($data['expected_answer'])) {
            return ['_error' => response()->json([
                'error' => 'Expected answer is required for fill-in-the-blank questions.',
            ], 422)];
        }
        unset($data['answer'], $data['options'], $data['pairs']);

        return $data;
    }

    private function prepareShortAnswer(array $data): array
    {
        if (empty($data['expected_answer'])) {
            return ['_error' => response()->json([
                'error' => 'Expected answer is required for short answer questions.',
            ], 422)];
        }
        unset($data['answer'], $data['options'], $data['pairs']);

        return $data;
    }

    private function prepareMatching(Request $request, array $data): array
    {
        $pairs = $request->input('pairs');
        // Make pairs optional/nullable. If provided, ensure it's an array; otherwise set to null.
        if ($pairs !== null && ! is_array($pairs)) {
            return ['_error' => response()->json([
                'error' => 'Pairs must be an array when provided.',
            ], 422)];
        }
        $data['pairs'] = $pairs ?: null;
        unset($data['answer'], $data['options'], $data['expected_answer']);

        return $data;
    }
}
