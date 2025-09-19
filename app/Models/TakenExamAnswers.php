<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TakenExamAnswers extends Model
{
    protected $fillable = [
        'id',
        'taken_exam_id',
        'exam_item_id',
        'type',
        'answer',
    ];

    protected $casts = [
        /**
         * Answer can be:
         * - int (mcq index)
         * - bool (true/false)
         * - string (short/essay/fill_blank)
         * - array (matching pairs)
         */
        'answer' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function takenExam()
    {
        return $this->belongsTo(TakenExam::class);
    }

    public function item()
    {
        return $this->belongsTo(ExamItem::class, 'exam_item_id');
    }

}
