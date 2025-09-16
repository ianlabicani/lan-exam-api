<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExamItemController extends Controller
{
    public function index(Exam $exam): JsonResponse
    {
        return response()->json($exam->items);
    }

    public function store(Request $request, Exam $exam): JsonResponse
    {
        if (in_array($exam->status, ['active', 'archived'])) {
            return response()->json(['message' => 'Cannot add items to an active or archived exam.'], 422);
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
            'options.*.correct' => 'required_with:options|boolean',
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

        $total = $exam->items()->sum('points');
        if ($exam->total_points !== $total) {
            $exam->update(['total_points' => $total]);
        }

        return response()->json([
            'item' => $item,
        ], 201);
    }

    public function update(Request $request, Exam $exam, $itemId): JsonResponse
    {
        if (in_array($exam->status, ['active', 'archived'])) {
            return response()->json(['message' => 'Cannot modify items of an active or archived exam.'], 422);
        }

        $item = $exam->items()->findOrFail($itemId);

        $payload = $request->validate([
            'type' => 'sometimes|string|in:mcq,truefalse,fillblank,shortanswer,essay,matching',
            'level' => 'sometimes|string|in:easy,moderate,difficult',
            'question' => 'sometimes|string',
            'points' => 'sometimes|integer|min:1',
            'expected_answer' => 'nullable|string',
            'answer' => 'nullable|string',
            'options' => 'nullable|array',
            'options.*.text' => 'required_with:options|string',
            'options.*.correct' => 'required_with:options|boolean',
            'pairs' => 'nullable|array',
            'pairs.*.left' => 'required_with:pairs|string',
            'pairs.*.right' => 'required_with:pairs|string',
        ]);

        // Merge current item data with incoming payload so helpers can operate on a full dataset
        $data = array_merge($item->toArray(), $payload);

        $type = $payload['type'] ?? $item->type;

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
            'level' => $data['level'] ?? $item->level,
            'question' => $data['question'] ?? $item->question,
            'points' => $data['points'] ?? $item->points,
            'expected_answer' => $data['expected_answer'] ?? null,
            'answer' => $data['answer'] ?? null,
            'options' => $data['options'] ?? null,
            'pairs' => $data['pairs'] ?? null,
        ];

        $item->update($updateData);

        // Recalculate total points for the exam
        $total = $exam->items()->sum('points');
        if ($exam->total_points !== $total) {
            $exam->update(['total_points' => $total]);
        }

        return response()->json([
            'item' => $item->fresh(),
        ]);
    }

    public function destroy(Exam $exam, $itemId): JsonResponse
    {
        if (in_array($exam->status, ['active', 'archived'])) {
            return response()->json(['message' => 'Cannot delete items of an active or archived exam.'], 422);
        }

        $item = $exam->items()->findOrFail($itemId);

        $item->delete();

        // Recalculate total points for the exam
        $total = $exam->items()->sum('points');
        if ($exam->total_points !== $total) {
            $exam->update(['total_points' => $total]);
        }

        return response()->json(['success' => true]);
    }

    // validateBase removed; validation is done inline in store().

    private function prepareMcq(Request $request, array $data): array
    {
        $options = $request->input('options', []);

        if (empty($options) || !is_array($options)) {
            return ['_error' => response()->json(['errors' => ['options' => ['Options are required for multiple choice questions.']]], 422)];
        }
        $hasCorrect = collect($options)->contains(fn($o) => isset($o['correct']) && $o['correct']);

        if (!$hasCorrect) {
            return ['_error' => response()->json(['errors' => ['options' => ['At least one option must be marked correct.']]], 422)];
        }

        $data['options'] = $options;
        unset($data['answer']);
        return $data;
    }

    private function prepareTrueFalse(Request $request, array $data): array
    {
        $value = $request->input('answer', $data['answer'] ?? null);

        if ($value === null) {
            return ['_error' => response()->json(['errors' => ['answer' => ['Answer is required for true/false questions.']]], 422)];
        }

        if ($value === true) {
            $value = 'true';
        } elseif ($value === false) {
            $value = 'false';
        } elseif (!is_string($value)) {
            return ['_error' => response()->json(['errors' => ['answer' => ['Answer must be the string "true" or "false".']]], 422)];
        }

        $normalized = strtolower(trim((string) $value));
        if (!in_array($normalized, ['true', 'false'], true)) {
            return ['_error' => response()->json(['errors' => ['answer' => ['Answer must be the string "true" or "false".']]], 422)];
        }

        $data['answer'] = $normalized;
        $data['options'] = null;
        $data['pairs'] = null;

        return $data;
    }

    private function prepareEssay(array $data): array
    {
        if (empty($data['expected_answer'])) {
            return ['_error' => response()->json(['errors' => ['expected_answer' => ['Expected answer (reference) is required for essay questions.']]], 422)];
        }
        unset($data['answer'], $data['options']);
        return $data;
    }

    private function prepareFillBlank(array $data): array
    {
        if (empty($data['expected_answer'])) {
            return ['_error' => response()->json(['errors' => ['expected_answer' => ['Expected answer is required for fill-in-the-blank questions.']]], 422)];
        }
        unset($data['answer'], $data['options'], $data['pairs']);
        return $data;
    }

    private function prepareShortAnswer(array $data): array
    {
        if (empty($data['expected_answer'])) {
            return ['_error' => response()->json(['errors' => ['expected_answer' => ['Expected answer is required for short answer questions.']]], 422)];
        }
        unset($data['answer'], $data['options'], $data['pairs']);
        return $data;
    }

    private function prepareMatching(Request $request, array $data): array
    {
        $pairs = $request->input('pairs');
        // Make pairs optional/nullable. If provided, ensure it's an array; otherwise set to null.
        if ($pairs !== null && !is_array($pairs)) {
            return ['_error' => response()->json(['errors' => ['pairs' => ['Pairs must be an array when provided.']]], 422)];
        }
        $data['pairs'] = $pairs ?: null;
        unset($data['answer'], $data['options'], $data['expected_answer']);
        return $data;
    }

}
