<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TakenExamAnswer extends Model
{
    protected $fillable = [
        'taken_exam_id',
        'exam_item_id',
        'answer',
        'points_earned',
        'feedback',
    ];

    protected $casts = [
        'points_earned' => 'float',
    ];

    /**
     * Get the taken exam that owns this answer.
     */
    public function takenExam(): BelongsTo
    {
        return $this->belongsTo(TakenExam::class);
    }

    /**
     * Get the exam item (question) this answer belongs to.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ExamItem::class, 'exam_item_id');
    }

    /**
     * Get the exam item (alias).
     */
    public function examItem(): BelongsTo
    {
        return $this->belongsTo(ExamItem::class, 'exam_item_id');
    }

    /**
     * Check if the answer is correct based on the exam item.
     */
    public function isCorrect(): ?bool
    {
        if (!$this->item) {
            return null;
        }

        $correctAnswer = $this->getCorrectAnswer();

        if ($correctAnswer === null || $correctAnswer === 'Manual grading required') {
            return null;
        }

        return $this->checkAnswer($correctAnswer);
    }

    /**
     * Get the correct answer for this item.
     */
    private function getCorrectAnswer()
    {
        $item = $this->item;

        switch ($item->type) {
            case 'mcq':
                $options = collect($item->options ?? []);
                $correctIndex = $options->search(function ($opt) {
                    return is_array($opt)
                        ? (!empty($opt['correct']))
                        : (!empty($opt->correct));
                });
                return $correctIndex !== false ? $correctIndex : null;

            case 'truefalse':
                return $item->answer;

            case 'matching':
                return $item->pairs;

            case 'fillblank':
            case 'fill_blank':
            case 'shortanswer':
                return $item->expected_answer;

            case 'essay':
                return 'Manual grading required';

            default:
                return null;
        }
    }

    /**
     * Check if student answer matches correct answer.
     */
    private function checkAnswer($correctAnswer): bool
    {
        $item = $this->item;

        switch ($item->type) {
            case 'mcq':
                return (int) $this->answer === (int) $correctAnswer;

            case 'truefalse':
                $expected = strtolower(trim((string) $correctAnswer));
                $student = strtolower(trim((string) $this->answer));
                return $expected === $student;

            case 'matching':
                // For matching, we can't use binary correct/incorrect since each pair scores individually
                // This method returns true only if ALL pairs are correct (for display purposes)
                if (!is_string($this->answer)) {
                    return false;
                }

                $studentPairs = json_decode($this->answer, true);
                if (!is_array($studentPairs) || !is_array($correctAnswer)) {
                    return false;
                }

                // Check if all pairs match exactly
                foreach ($studentPairs as $leftIndex => $rightIndex) {
                    if (!isset($correctAnswer[$leftIndex]) || $correctAnswer[$leftIndex]['right'] !== $correctAnswer[$rightIndex]['right']) {
                        return false;
                    }
                }

                return true;

            case 'fillblank':
            case 'fill_blank':
            case 'shortanswer':
                return strtolower(trim((string) $this->answer)) === strtolower(trim((string) $correctAnswer));

            default:
                return false;
        }
    }
}
