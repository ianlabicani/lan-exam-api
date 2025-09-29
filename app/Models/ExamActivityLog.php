<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamActivityLog extends Model
{
    protected $fillable = [
        'taken_exam_id',
        'student_id',
        'event_type',
        'details'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
