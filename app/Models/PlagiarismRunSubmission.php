<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlagiarismRunSubmission extends Model
{
    protected $fillable = [
        'ai_processing_run_id',
        'submission_id',
        'student_id',
        'ordinal',
        'content',
        'content_hash',
    ];

    public function run()
    {
        return $this->belongsTo(AiProcessingRun::class, 'ai_processing_run_id');
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
