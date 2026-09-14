<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = ['assignment_id', 'student_id', 'content', 'answers', 'submitted_at', 'started_at', 'expires_at', 'terms_accepted_at', 'status'];

    protected $casts = [
        'answers' => 'array',
        'submitted_at' => 'datetime',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function grade()
    {
        return $this->hasOne(Grade::class);
    }

    public function aiFeedback()
    {
        return $this->hasOne(AiFeedback::class);
    }

    public function proctoringEvents()
    {
        return $this->hasMany(ProctoringEvent::class);
    }

    public function isGraded(): bool
    {
        return in_array($this->status, ['graded', 'approved']);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isActiveExamAttempt(): bool
    {
        return $this->started_at !== null
            && ! $this->isApproved()
            && ($this->expires_at === null || now()->lessThanOrEqualTo($this->expires_at));
    }
}
