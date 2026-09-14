<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProctoringEvent extends Model
{
    protected $fillable = ['submission_id', 'event_type', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }
}