<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\TakenExam;
use Illuminate\Http\Request;

class TakenExamController extends Controller
{
    public function index(Exam $exam)
    {
        $takenExams = TakenExam::with(['user', 'answers.item', 'exam.items'])
            ->where('exam_id', $exam->id)
            ->get()
            ->map(function ($takenExam) {
                // Compare answers for each takenExam
                $takenExam->answer_comparison = $this->compareAnswers($takenExam->exam->items, $takenExam->answers);
                return $takenExam;
            });

        return response()->json(['data' => $takenExams]);
    }

    public function show(Exam $exam, TakenExam $takenExam)
    {
        $takenExam = TakenExam::with(['user', 'answers.item', 'exam.items'])
            ->where('exam_id', $exam->id)
            ->where('user_id', $takenExam->user_id)
            ->firstOrFail();

        // Compare exam items with student answers
        $comparison = $this->compareAnswers($takenExam->exam->items, $takenExam->answers);

        return response()->json([
            'data' => $takenExam,
            'answer_comparison' => $comparison
        ]);
    }

    /**
     * Compare exam items with student answers
     */
    private function compareAnswers($examItems, $studentAnswers)
    {
        // Create a lookup for student answers by exam_item_id
        $answerLookup = $studentAnswers->keyBy('exam_item_id');

        return $examItems->map(function ($item) use ($answerLookup) {
            $studentAnswer = $answerLookup->get($item->id);
            $correctAnswer = $this->getCorrectAnswer($item);

            $isCorrect = false;
            $studentResponse = null;

            if ($studentAnswer) {
                $studentResponse = $studentAnswer->answer;
                $isCorrect = $this->checkAnswer($item, $studentAnswer->answer, $correctAnswer);
            }

            return [
                'exam_item_id' => $item->id,
                'type' => $item->type,
                'question' => $item->question,
                'points' => $item->points,
                'correct_answer' => $correctAnswer,
                'student_answer' => $studentResponse,
                'is_correct' => $isCorrect,
                'answered' => $studentAnswer !== null
            ];
        });
    }

    /**
     * Get correct answer for a single exam item
     */
    private function getCorrectAnswer($item)
    {
        switch ($item->type) {
            case 'mcq':
                $options = collect($item->options ?? []);
                $correctIndex = $options->search(function ($opt) {
                    return is_array($opt)
                        ? (!empty($opt['correct']))
                        : (!empty($opt->correct));
                });
                return $correctIndex !== false ? $correctIndex : null;

            case 'truefalse': {
                $value = strtolower(trim((string) $item->answer));

                if (in_array($value, ['true', '1', 'yes'], true)) {
                    return true;
                }

                if (in_array($value, ['false', '0', 'no'], true)) {
                    return false;
                }

                return null; // Unknown / unset
            }

            case 'matching':
                return $item->pairs;

            case 'fill_blank':
            case 'fillblank':
            case 'shortanswer':
                return $item->answer;

            case 'essay':
                return 'Manual grading required';

            default:
                return null;
        }
    }

    /**
     * Check if student answer is correct
     */
    private function checkAnswer($item, $studentAnswer, $correctAnswer)
    {
        if ($correctAnswer === null || $correctAnswer === 'Manual grading required') {
            return null; // Cannot auto-check
        }

        switch ($item->type) {
            case 'mcq':
                return (int) $studentAnswer === (int) $correctAnswer;

            case 'truefalse':
                $expected = strtolower((string) $correctAnswer);
                $expectedBool = in_array($expected, ['true', '1', 'yes'], true);
                return (bool) $studentAnswer === $expectedBool;

            case 'matching':
                return $studentAnswer === $correctAnswer;

            case 'fill_blank':
            case 'fillblank':
            case 'shortanswer':
                return strtolower(trim((string) $studentAnswer)) === strtolower(trim((string) $correctAnswer));

            case 'essay':
                return null; // Manual grading

            default:
                return null;
        }
    }
}
