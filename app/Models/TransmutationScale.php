<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransmutationScale extends Model
{
    protected $fillable = ['section_id', 'min_score', 'max_score', 'grade', 'description'];

    protected $casts = [
        'min_score' => 'float',
        'max_score' => 'float',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public static function defaultScale(): array
    {
        return [
            ['min' => 97, 'max' => 100, 'grade' => '1.00', 'description' => 'Excellent'],
            ['min' => 94, 'max' => 96,  'grade' => '1.25', 'description' => 'Excellent'],
            ['min' => 91, 'max' => 93,  'grade' => '1.50', 'description' => 'Very Good'],
            ['min' => 88, 'max' => 90,  'grade' => '1.75', 'description' => 'Very Good'],
            ['min' => 85, 'max' => 87,  'grade' => '2.00', 'description' => 'Good'],
            ['min' => 82, 'max' => 84,  'grade' => '2.25', 'description' => 'Good'],
            ['min' => 79, 'max' => 81,  'grade' => '2.50', 'description' => 'Satisfactory'],
            ['min' => 76, 'max' => 78,  'grade' => '2.75', 'description' => 'Satisfactory'],
            ['min' => 75, 'max' => 75,  'grade' => '3.00', 'description' => 'Passing'],
            ['min' => 0,  'max' => 74,  'grade' => '5.00', 'description' => 'Failed'],
        ];
    }

    public static function transmute(float $score, int $sectionId): string
    {
        $scale = static::where('section_id', $sectionId)->orderByDesc('min_score')->get();

        if ($scale->isEmpty()) {
            foreach (static::defaultScale() as $row) {
                if ($score >= $row['min'] && $score <= $row['max']) {
                    return $row['grade'];
                }
            }
            return '5.00';
        }

        foreach ($scale as $row) {
            if ($score >= $row->min_score && $score <= $row->max_score) {
                return $row->grade;
            }
        }

        return '5.00';
    }
}
