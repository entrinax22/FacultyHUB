<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlagiarismRunPair extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'ai_processing_run_id',
        'snapshot_a_id',
        'snapshot_b_id',
        'status',
        'attempt_count',
        'next_eligible_at',
        'error_code',
        'similarity_score',
        'flagged',
        'explanation',
    ];

    protected $casts = [
        'next_eligible_at' => 'datetime',
        'similarity_score' => 'float',
        'flagged' => 'boolean',
    ];

    public function run()
    {
        return $this->belongsTo(AiProcessingRun::class, 'ai_processing_run_id');
    }

    public function snapshotA()
    {
        return $this->belongsTo(PlagiarismRunSubmission::class, 'snapshot_a_id');
    }

    public function snapshotB()
    {
        return $this->belongsTo(PlagiarismRunSubmission::class, 'snapshot_b_id');
    }
}
