<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProcessingBudgetWindow extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'category',
        'window_type',
        'window_starts_at',
        'request_count',
        'estimated_input_tokens',
    ];

    protected $casts = [
        'window_starts_at' => 'datetime',
    ];
}
