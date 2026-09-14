<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $fillable = [
        'enabled',
        'provider',
        'api_key',
        'model',
        'temperature',
        'max_output_tokens',
        'include_assignment_instructions',
        'include_rubric',
        'context_enabled',
        'global_context',
        'essay_context',
        'code_context',
        'plagiarism_context',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'enabled' => 'boolean',
        'api_key' => 'encrypted',
        'temperature' => 'decimal:2',
        'max_output_tokens' => 'integer',
        'include_assignment_instructions' => 'boolean',
        'include_rubric' => 'boolean',
        'context_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'enabled' => true,
            'provider' => 'gemini',
            'model' => config('services.gemini.model', 'gemini-3-flash-preview'),
            'temperature' => 0.10,
            'max_output_tokens' => 4096,
            'include_assignment_instructions' => true,
            'include_rubric' => true,
            'context_enabled' => true,
        ]);
    }
}
