<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['section_id', 'title', 'description', 'week_number', 'order', 'is_published'];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function files()
    {
        return $this->hasMany(ModuleFile::class);
    }

    public function progress()
    {
        return $this->hasMany(ModuleProgress::class);
    }

    public function isReadByStudent(int $studentId): bool
    {
        return $this->progress()->where('student_id', $studentId)->exists();
    }
}
