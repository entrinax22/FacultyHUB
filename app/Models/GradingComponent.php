<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingComponent extends Model
{
    protected $fillable = ['section_id', 'name', 'weight_percentage', 'max_score', 'order', 'is_locked'];

    protected $casts = [
        'weight_percentage' => 'float',
        'max_score' => 'float',
        'is_locked' => 'boolean',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class, 'component_id');
    }

    public function items()
    {
        return $this->hasMany(GradingItem::class, 'component_id');
    }
}
