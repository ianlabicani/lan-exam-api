<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamActivityLog extends Model
{
    protected $fillable = [
        'taken_exam_id',
        'student_id',
        'event_type',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Get the taken exam that this activity log belongs to
     */
    public function takenExam(): BelongsTo
    {
        return $this->belongsTo(TakenExam::class);
    }

    /**
     * Get the student (user) who triggered this activity
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
