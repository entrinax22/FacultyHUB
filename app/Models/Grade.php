<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $fillable = [
        'submission_id', 'student_id', 'section_id', 'assignment_id',
        'component_id', 'raw_score', 'max_score', 'remarks', 'is_released',
    ];

    protected $casts = [
        'raw_score' => 'float',
        'max_score' => 'float',
        'is_released' => 'boolean',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function component()
    {
        return $this->belongsTo(GradingComponent::class, 'component_id');
    }

    public function getPercentageAttribute(): float
    {
        if ($this->max_score == 0) return 0;

        return round(($this->raw_score / $this->max_score) * 100, 2);
    }
}
