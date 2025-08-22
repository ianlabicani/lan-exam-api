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
        'section',
        'status',
        'total_points',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];


    public function teachers()
    {
        return $this->belongsToMany(User::class, 'exam_teacher', 'exam_id', 'teacher_id');
    }

    public function items()
    {
        return $this->hasMany(ExamItem::class);
    }
}
