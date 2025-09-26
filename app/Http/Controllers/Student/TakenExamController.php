<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\TakenExam;
use Illuminate\Http\Request;

class TakenExamController extends Controller
{

    public function index(Request $request)
    {
        $user = $request->user();
        $takenExams = TakenExam::with('exam')
            ->where('user_id', $user->id)
            ->get();

        return response()->json(['data' => $takenExams]);
    }

    public function store(Request $request, $examId)
    {
        $userId = $request->user()->id;

        // for getting the completed exam attempt

        $completedAttempt = TakenExam::where('exam_id', $examId)
            ->where('user_id', $userId)
            ->whereNotNull('submitted_at')
            ->first();

        if ($completedAttempt) {
            $completedAttempt->load(['answers', 'exam']);

            return response()->json([
                'takenExam' => $completedAttempt,
                'message' => 'You have already submitted this exam and cannot start a new attempt.',
            ], 200);
        }

        // for getting the ongoing exam

        $takenExam = TakenExam::where('exam_id', $examId)
            ->where('user_id', $userId)
            ->whereNull('submitted_at')
            ->first();

        if ($takenExam) {
            $takenExam->load(['answers', 'exam']);
            return response()->json(['takenExam' => $takenExam]);
        }

        // for a new exam attempt

        $attempt = TakenExam::create([
            'exam_id' => $examId,
            'user_id' => $userId,
            'started_at' => now(),
            'total_points' => 0,
        ]);

        $attempt->load('exam');
        return response()->json(['data' => $attempt], 201);
    }


    public function finish(Request $request, $id)
    {
        $takenExam = TakenExam::with(['answers.item', 'exam'])->findOrFail($id);

        // Compute score
        $score = 0;
        foreach ($takenExam->answers as $answer) {
            if ($answer->type === 'mcq') {
                // Options may be stored as array (JSON cast); wrap in collection
                $options = collect($answer->item->options ?? []);
                if ($options->isNotEmpty()) {
                    // Find index of the first correct option (supports array or object items)
                    $correctIndex = $options->search(function ($opt) {
                        return is_array($opt)
                            ? (!empty($opt['correct']))
                            : (!empty($opt->correct));
                    });
                    if ($correctIndex !== false && (int) $answer->answer === (int) $correctIndex) {
                        $score += (int) $answer->item->points;
                    }
                }
            } elseif ($answer->type === 'truefalse') {
                $expected = strtolower((string) ($answer->item->expected_answer ?? ''));
                $expectedBool = in_array($expected, ['true', '1', 'yes'], true);
                if ((bool) $answer->answer === $expectedBool) {
                    $score += $answer->item->points;
                }
            }
            // essay will be graded manually later
        }

        $takenExam->update([
            'submitted_at' => now(),
            'total_points' => $score,
        ]);

        return response()->json($takenExam);
    }
    /**
     * Display a taken exam with its related exam.
     */
    public function show(int $id)
    {
        $takenExam = TakenExam::with('exam', 'answers')->findOrFail($id);

        return response()->json(['data' => $takenExam]);
    }

}
