<?php

namespace App\Models;

use App\Enums\Shift;
use App\Enums\Weekday;
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

    protected $fillable = [
        'academic_year_id', 'number', 'starts_on', 'ends_on', 'first_week_parity',
        'first_saturday_weekday', 'first_shift_saturdays', 'second_shift_saturdays',
    ];

    protected $attributes = [
        'first_week_parity' => 'numerator',
        'first_saturday_weekday' => 1,
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'first_week_parity' => WeekParity::class,
            'first_saturday_weekday' => Weekday::class,
            'first_shift_saturdays' => 'array',
            'second_shift_saturdays' => 'array',
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

    /** @return list<CarbonImmutable> усі суботи семестру */
    public function saturdays(): array
    {
        $saturdays = [];
        for ($d = $this->firstSaturday(); $d->lte($this->ends_on); $d = $d->addWeek()) {
            $saturdays[] = $d;
        }

        return $saturdays;
    }

    /**
     * За розкладом якого дня йде субота: перша субота семестру — за first_saturday_weekday,
     * кожна наступна — за наступним днем Пн–Пт по колу. Рахується за календарем:
     * вихідна субота теж «забирає» свій день.
     */
    public function saturdayWeekday(CarbonInterface $saturday): Weekday
    {
        $start = ($this->first_saturday_weekday ?? Weekday::Monday)->value - 1;
        $n = (int) round($this->firstSaturday()->diffInWeeks(CarbonImmutable::parse($saturday)->startOfDay(), absolute: true));
        $days = Weekday::schoolDays();

        return $days[($start + $n) % count($days)];
    }

    /** @return list<Shift> зміни, для яких субота навчальна */
    public function saturdayShifts(CarbonInterface $saturday): array
    {
        $date = CarbonImmutable::parse($saturday)->toDateString();

        return array_values(array_filter([
            in_array($date, $this->first_shift_saturdays ?? [], true) ? Shift::First : null,
            in_array($date, $this->second_shift_saturdays ?? [], true) ? Shift::Second : null,
        ]));
    }

    private function firstSaturday(): CarbonImmutable
    {
        return $this->starts_on->dayOfWeekIso === 6 ? $this->starts_on : $this->starts_on->next(CarbonImmutable::SATURDAY);
    }

    private static function flip(WeekParity $parity): WeekParity
    {
        return $parity === WeekParity::Numerator ? WeekParity::Denominator : WeekParity::Numerator;
    }
}
