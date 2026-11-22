<?php

namespace App\Models;

use App\Enums\ConstraintLevel;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherConstraint extends Model
{
    protected $fillable = ['teacher_id', 'weekday', 'pair', 'week', 'level', 'starts_on', 'ends_on', 'note'];

    protected $attributes = [
        'week' => 'every',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'pair' => 'integer',
            'week' => WeekParity::class,
            'level' => ConstraintLevel::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function isTemporary(): bool
    {
        return $this->starts_on !== null || $this->ends_on !== null;
    }

    /** Чи діє обмеження в цю дату (постійне — завжди). */
    public function activeOn(CarbonInterface $date): bool
    {
        return ($this->starts_on === null || $this->starts_on->lte($date))
            && ($this->ends_on === null || $this->ends_on->gte($date));
    }

    public function covers(Weekday $weekday, int $pair, WeekParity $week): bool
    {
        return $this->weekday === $weekday
            && ($this->pair === null || $this->pair === $pair)
            && $this->week->overlaps($week);
    }
}
