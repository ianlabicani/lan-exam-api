<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = [
        'title',
        'description',
        'starts_at',
        'ends_at',
        'year',
        'sections',
        'status',
        'total_points',
        'tos',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sections' => 'array',
        'tos' => 'array',
    ];


    public function teachers()
    {
        return $this->belongsToMany(User::class, 'exam_teacher', 'exam_id', 'teacher_id');
    }

    public function items()
    {
        return $this->hasMany(ExamItem::class);
    }

    public function takenExams()
    {
        return $this->hasMany(TakenExam::class);
    }
}
