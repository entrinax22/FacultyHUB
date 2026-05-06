<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = ['section_id', 'created_by', 'date', 'topic', 'is_closed'];

    protected $casts = [
        'date' => 'date',
        'is_closed' => 'boolean',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function records()
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }
}
