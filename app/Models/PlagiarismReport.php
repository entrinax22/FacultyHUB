<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlagiarismReport extends Model
{
    protected $fillable = [
        'assignment_id', 'student_a_id', 'student_b_id',
        'similarity_score', 'flagged', 'explanation',
    ];

    protected $casts = [
        'similarity_score' => 'float',
        'flagged' => 'boolean',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function studentA()
    {
        return $this->belongsTo(Student::class, 'student_a_id');
    }

    public function studentB()
    {
        return $this->belongsTo(Student::class, 'student_b_id');
    }
}
