<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TakenExam extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'started_at',
        'submitted_at',
        'total_points',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'total_points' => 'integer',
    ];

    /**
     * Get the exam that was taken.
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the user (student) who took the exam.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the student who took the exam (alias for user).
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all answers for this taken exam.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(TakenExamAnswer::class);
    }

    /**
     * Check if the exam has been started.
     */
    public function isStarted(): bool
    {
        return $this->started_at !== null;
    }

    /**
     * Check if the exam has been submitted.
     */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Check if the exam is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->isStarted() && ! $this->isSubmitted();
    }

    /**
     * Get the duration of the exam in minutes.
     */
    public function getDurationInMinutes(): ?int
    {
        if (! $this->started_at || ! $this->submitted_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->submitted_at);
    }

    /**
     * Get the score percentage.
     */
    public function getScorePercentage(): ?float
    {
        if (! $this->exam || $this->exam->total_points == 0) {
            return null;
        }

        return ($this->total_points / $this->exam->total_points) * 100;
    }

    /**
     * Check if the exam is passed (assuming 60% is passing).
     */
    public function isPassed(float $passingPercentage = 60.0): bool
    {
        $percentage = $this->getScorePercentage();

        if ($percentage === null) {
            return false;
        }

        return $percentage >= $passingPercentage;
    }

    /**
     * Get the status of the taken exam.
     */
    public function getStatus(): string
    {
        if (! $this->isStarted()) {
            return 'not_started';
        }

        if ($this->isInProgress()) {
            return 'in_progress';
        }

        if ($this->isSubmitted()) {
            return 'submitted';
        }

        return 'unknown';
    }

    /**
     * Scope to get only submitted exams.
     */
    public function scopeSubmitted($query)
    {
        return $query->whereNotNull('submitted_at');
    }

    /**
     * Scope to get only in-progress exams.
     */
    public function scopeInProgress($query)
    {
        return $query->whereNotNull('started_at')
            ->whereNull('submitted_at');
    }

    /**
     * Scope to get exams for a specific student.
     */
    public function scopeForStudent($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get exams for a specific exam.
     */
    public function scopeForExam($query, $examId)
    {
        return $query->where('exam_id', $examId);
    }
}
