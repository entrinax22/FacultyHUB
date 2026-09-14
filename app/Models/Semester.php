<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    protected $fillable = ['name', 'school_year', 'start_date', 'end_date', 'is_active'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
