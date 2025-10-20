<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamItem extends Model
{
    protected $fillable = [
        'exam_id',
        'topic',            // topic name for organization
        'type',             // mcq, truefalse, fill_blank (legacy: fillblank), shortanswer, essay, matching
        'level',            // easy, average, difficult
        'question',
        'points',
        'expected_answer',  // text/expected value
        'answer',           // student answer (string, nullable)
        'options',          // for mcq/truefalse
        'pairs',            // for matching
    ];

    protected $casts = [
        'options' => 'array',   // stores options as JSON
        'pairs' => 'array',   // stores left-right matching pairs as JSON
        'answer' => 'string',  // student’s answer (can be null)
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
