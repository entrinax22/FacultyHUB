<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = ['name', 'semester_id', 'subject_id', 'faculty_id', 'schedule', 'room'];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function faculty()
    {
        return $this->belongsTo(User::class, 'faculty_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students()
    {
        return $this->hasManyThrough(Student::class, Enrollment::class, 'section_id', 'id', 'id', 'student_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('order')->orderBy('week_number');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function gradingComponents()
    {
        return $this->hasMany(GradingComponent::class)->orderBy('order');
    }

    public function transmutationScales()
    {
        return $this->hasMany(TransmutationScale::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class)->orderByDesc('date');
    }
}
