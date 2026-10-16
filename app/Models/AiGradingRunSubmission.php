<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGradingRunSubmission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'ai_processing_run_id',
        'submission_id',
        'content',
        'content_hash',
        'status',
        'attempt_count',
        'next_eligible_at',
        'lease_token',
        'lease_expires_at',
        'result',
        'error_code',
    ];

    protected $casts = [
        'next_eligible_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'result' => 'array',
    ];

    public function run()
    {
        return $this->belongsTo(AiProcessingRun::class, 'ai_processing_run_id');
    }
}
