<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingItem extends Model
{
    protected $fillable = [
        'section_id',
        'component_id',
        'name',
        'max_score',
        'order',
        'is_enabled',
    ];

    protected $casts = [
        'max_score' => 'float',
        'is_enabled' => 'boolean',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function component()
    {
        return $this->belongsTo(GradingComponent::class, 'component_id');
    }

    public function scores()
    {
        return $this->hasMany(GradingItemScore::class);
    }
}

