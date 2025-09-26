<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TakenExam extends Model
{

    protected $fillable = [
        'exam_id',
        'user_id',
        'started_at',
        'submitted_at',
        'total_points',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answers()
    {
        return $this->hasMany(TakenExamAnswers::class);
    }
}
