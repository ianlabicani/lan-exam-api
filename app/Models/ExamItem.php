<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamItem extends Model
{
    protected $fillable = [
        'exam_id',
        'type',
        'question',
        'points',
        'expected_answer',
        'answer',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
        'answer' => 'boolean',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
