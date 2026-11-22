<?php

namespace App\Models;

use App\Enums\WeekParity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = ['academic_year_id', 'number', 'starts_on', 'ends_on', 'first_week_parity'];

    protected $attributes = [
        'first_week_parity' => 'numerator',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'first_week_parity' => WeekParity::class,
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function parityOverrides(): HasMany
    {
        return $this->hasMany(WeekParityOverride::class)->orderBy('week_start');
    }

    public function contains(CarbonInterface $date): bool
    {
        return $date->betweenIncluded($this->starts_on, $this->ends_on);
    }

    public function label(): string
    {
        return "{$this->number} семестр {$this->academicYear?->title}";
    }

    /**
     * Тип тижня, що містить дату. Рахується за календарними тижнями від початку семестру
     * (канікули й практики чергування не збивають); перевизначення починає чергування заново.
     */
    public function parityOn(CarbonInterface $date): WeekParity
    {
        $week = CarbonImmutable::parse($date)->startOfWeek();
        [$anchorWeek, $anchorParity] = [$this->starts_on->startOfWeek(), $this->first_week_parity];

        foreach ($this->parityOverrides as $override) {
            if ($override->week_start->lte($week)) {
                [$anchorWeek, $anchorParity] = [$override->week_start, $override->parity];
            }
        }

        $weeks = (int) round($anchorWeek->diffInWeeks($week, absolute: true));

        return $weeks % 2 === 0 ? $anchorParity : self::flip($anchorParity);
    }

    /** Тип тижня без ручних перевизначень — щоб показати, де чергування зсунулось. */
    public function naturalParityOn(CarbonInterface $date): WeekParity
    {
        $weeks = (int) round($this->starts_on->startOfWeek()->diffInWeeks(CarbonImmutable::parse($date)->startOfWeek(), absolute: true));

        return $weeks % 2 === 0 ? $this->first_week_parity : self::flip($this->first_week_parity);
    }

    /** @return list<CarbonImmutable> понеділки всіх тижнів семестру */
    public function weekStarts(): array
    {
        $weeks = [];
        for ($w = $this->starts_on->startOfWeek(); $w->lte($this->ends_on); $w = $w->addWeek()) {
            $weeks[] = $w;
        }

        return $weeks;
    }

    private static function flip(WeekParity $parity): WeekParity
    {
        return $parity === WeekParity::Numerator ? WeekParity::Denominator : WeekParity::Numerator;
    }
}
