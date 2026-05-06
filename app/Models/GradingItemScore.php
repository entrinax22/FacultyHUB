<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingItemScore extends Model
{
    protected $fillable = [
        'grading_item_id',
        'student_id',
        'section_id',
        'score',
        'is_released',
    ];

    protected $casts = [
        'score' => 'float',
        'is_released' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(GradingItem::class, 'grading_item_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}

