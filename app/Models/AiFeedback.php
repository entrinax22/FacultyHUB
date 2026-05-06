<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFeedback extends Model
{
    protected $table = 'ai_feedback';

    protected $fillable = ['submission_id', 'score', 'feedback_json', 'generated_at', 'model_used'];

    protected $casts = [
        'feedback_json' => 'array',
        'generated_at' => 'datetime',
        'score' => 'float',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }
}
