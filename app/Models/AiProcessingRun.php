<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProcessingRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const TYPE_GRADING = 'grading';

    public const TYPE_PLAGIARISM = 'plagiarism';

    public const PREPARATION_PENDING = 'pending';

    public const PREPARATION_SNAPSHOTTING = 'snapshotting';

    public const PREPARATION_PAIRING = 'pairing';

    public const PREPARATION_PREPARED = 'prepared';

    public const PREPARATION_FAILED = 'failed';

    protected $fillable = [
        'owner_id',
        'resource_type',
        'resource_id',
        'processing_type',
        'status',
        'total_work_units',
        'completed_work_units',
        'failed_work_units',
        'retry_count',
        'max_retry_attempts',
        'error_code',
        'lease_token',
        'lease_expires_at',
        'started_at',
        'completed_at',
        'failed_at',
        'active_key',
        'preparation_status',
        'preparation_started_at',
        'preparation_completed_at',
        'preparation_failed_at',
        'published_at',
    ];

    protected $casts = [
        'lease_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'preparation_started_at' => 'datetime',
        'preparation_completed_at' => 'datetime',
        'preparation_failed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plagiarismSubmissions()
    {
        return $this->hasMany(PlagiarismRunSubmission::class);
    }

    public function plagiarismPairs()
    {
        return $this->hasMany(PlagiarismRunPair::class);
    }

    public function gradingSubmissions()
    {
        return $this->hasMany(AiGradingRunSubmission::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function isLeaseExpired(): bool
    {
        return $this->lease_expires_at?->isPast() ?? false;
    }
}
