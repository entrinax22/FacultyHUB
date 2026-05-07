<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\GradingComponent;
use App\Models\GradingItem;

class Assignment extends Model
{
    protected $fillable = [
        'section_id', 'module_id', 'component_id', 'period', 'category', 'title', 'instructions', 'type',
        'due_date', 'max_score', 'passing_score', 'is_published',
        'rubric', 'language', 'answer_release_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'answer_release_at' => 'datetime',
        'is_published' => 'boolean',
        'max_score' => 'float',
        'passing_score' => 'float',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function component()
    {
        return $this->belongsTo(GradingComponent::class, 'component_id');
    }

    public function gradingItem()
    {
        return $this->hasOne(GradingItem::class);
    }

    public function questions()
    {
        return $this->hasMany(AssignmentQuestion::class)->orderBy('order');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function plagiarismReports()
    {
        return $this->hasMany(PlagiarismReport::class);
    }

    public function isPastDue(): bool
    {
        return $this->due_date && now()->isAfter($this->due_date);
    }

    public function answersReleased(): bool
    {
        return $this->answer_release_at && now()->isAfter($this->answer_release_at);
    }
}
