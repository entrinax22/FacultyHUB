<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentQuestion extends Model
{
    protected $fillable = ['assignment_id', 'question', 'order', 'points'];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function choices()
    {
        return $this->hasMany(AssignmentChoice::class, 'question_id')->orderBy('order');
    }

    public function correctChoice()
    {
        return $this->hasOne(AssignmentChoice::class, 'question_id')->where('is_correct', true);
    }
}
