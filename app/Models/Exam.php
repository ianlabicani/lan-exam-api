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
        'year' => 'array',
        'sections' => 'array',
        'tos' => 'array',
    ];

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'exam_teacher', 'exam_id', 'teacher_id')->withTimestamps();
    }

    public function items()
    {
        return $this->hasMany(ExamItem::class);
    }

    public function takenExams()
    {
        return $this->hasMany(TakenExam::class);
    }

    public function takers()
    {
        return $this->belongsToMany(User::class, 'taken_exams', 'exam_id', 'user_id')
            ->withPivot(['started_at', 'submitted_at', 'total_points'])
            ->withTimestamps();
    }

    // Lifecycle status methods
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function isReady()
    {
        return $this->status === 'ready';
    }

    public function isPublished()
    {
        return $this->status === 'published';
    }

    public function isOngoing()
    {
        return $this->status === 'ongoing';
    }

    public function isClosed()
    {
        return $this->status === 'closed';
    }

    public function isGraded()
    {
        return $this->status === 'graded';
    }

    public function isArchived()
    {
        return $this->status === 'archived';
    }

    // Check if exam can be edited
    public function canBeEdited()
    {
        return in_array($this->status, ['draft', 'ready']);
    }

    // Check if exam is visible to students
    public function isVisibleToStudents()
    {
        return in_array($this->status, ['published', 'ongoing', 'closed', 'graded']);
    }

    // Check if exam is currently active (can be taken)
    public function isActive()
    {
        return $this->status === 'ongoing' &&
               now()->between($this->starts_at, $this->ends_at);
    }

    // Transition to next stage
    public function transitionTo($status)
    {
        $allowedTransitions = [
            'draft' => ['ready'],
            'ready' => ['published', 'draft'],
            'published' => ['ongoing', 'ready'],
            'ongoing' => ['closed'],
            'closed' => ['graded'],
            'graded' => ['archived'],
            'archived' => [],
        ];

        if (in_array($status, $allowedTransitions[$this->status] ?? [])) {
            $this->status = $status;
            return $this->save();
        }

        return false;
    }

    // Auto-update status based on time
    public function updateStatusBasedOnTime()
    {
        if ($this->status === 'published' && now()->gte($this->starts_at)) {
            $this->status = 'ongoing';
            $this->save();
        } elseif ($this->status === 'ongoing' && now()->gt($this->ends_at)) {
            $this->status = 'closed';
            $this->save();
        }
    }

    // Get lifecycle configuration
    public static function getLifecycleConfig()
    {
        return [
            'draft' => [
                'label' => 'Draft',
                'description' => 'The exam is being created or edited by the teacher but is not yet visible to students.',
                'visibility' => ['teacher' => true, 'student' => false],
            ],
            'ready' => [
                'label' => 'Ready for Review',
                'description' => 'The exam is complete and ready for teacher review before publishing.',
                'visibility' => ['teacher' => true, 'student' => false],
            ],
            'published' => [
                'label' => 'Published',
                'description' => 'The exam is officially scheduled and visible to students but not yet active.',
                'visibility' => ['teacher' => true, 'student' => true],
            ],
            'ongoing' => [
                'label' => 'Ongoing',
                'description' => 'The exam is currently active and available for students to take.',
                'visibility' => ['teacher' => true, 'student' => true],
            ],
            'closed' => [
                'label' => 'Closed',
                'description' => 'The exam has ended. Students can no longer submit answers.',
                'visibility' => ['teacher' => true, 'student' => true],
            ],
            'graded' => [
                'label' => 'Graded',
                'description' => 'All exam submissions have been graded and results are available.',
                'visibility' => ['teacher' => true, 'student' => true],
            ],
            'archived' => [
                'label' => 'Archived',
                'description' => 'The exam is completed and archived for record-keeping or future reference.',
                'visibility' => ['teacher' => true, 'student' => false],
            ],
        ];
    }

    public function getLifecycleLabel()
    {
        $config = self::getLifecycleConfig();
        return $config[$this->status]['label'] ?? $this->status;
    }
}
