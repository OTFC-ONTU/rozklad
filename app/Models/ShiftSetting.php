<?php

namespace App\Models;

use App\Enums\Shift;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Налаштування змін, що діють з дати. Змін завжди дві: курси з second_shift_courses
 * навчаються в 2 зміну, решта — в 1. Якщо 2 зміну вимкнено (напр., 4 курс на практиці),
 * усі групи тимчасово навчаються в 1 зміну, а тижневий розклад не змінюється.
 *
 * Пари 1–3 доступні завжди, 4-а — за перемикачем зміни. 5-ї пари не буває.
 * Навчальні суботи обирають у семестрі (Semester::saturdayShifts).
 */
class ShiftSetting extends Model
{
    public const int MAX_PAIRS = 4;

    public const array COURSES = [1, 2, 3, 4];

    protected $fillable = [
        'effective_from', 'second_shift_courses', 'second_shift_enabled',
        'first_fourth_pair_enabled', 'second_fourth_pair_enabled',
    ];

    protected $attributes = [
        'second_shift_courses' => '[]',
        'second_shift_enabled' => true,
        'first_fourth_pair_enabled' => true,
        'second_fourth_pair_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'second_shift_courses' => 'array',
            'second_shift_enabled' => 'boolean',
            'first_fourth_pair_enabled' => 'boolean',
            'second_fourth_pair_enabled' => 'boolean',
        ];
    }

    /** Налаштування, що діють на дату; якщо записів немає — типові (усі курси в 1 зміні). */
    public static function for(CarbonInterface $date): self
    {
        return static::query()
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first()
            ?? new static;
    }

    /** Найновіший запис — основа для нового. */
    public static function newest(): ?self
    {
        return static::query()->orderByDesc('effective_from')->first();
    }

    /** Зміна курсу за розкладом — без урахування вимкнення 2 зміни. */
    public function scheduledShift(int $course): Shift
    {
        return in_array($course, $this->secondShiftCourses(), true) ? Shift::Second : Shift::First;
    }

    /** Фактична зміна курсу: коли 2 зміну вимкнено, усі в 1. */
    public function shiftOf(int $course): Shift
    {
        return $this->second_shift_enabled ? $this->scheduledShift($course) : Shift::First;
    }

    /** @return list<Shift> зміни, що працюють */
    public function activeShifts(): array
    {
        return $this->second_shift_enabled ? Shift::cases() : [Shift::First];
    }

    /** @return list<int> */
    public function secondShiftCourses(): array
    {
        return array_values(array_map('intval', $this->second_shift_courses ?? []));
    }

    /** @return list<int> */
    public function coursesOf(Shift $shift): array
    {
        return array_values(array_filter(self::COURSES, fn (int $c) => $this->scheduledShift($c) === $shift));
    }

    public function allows(Shift $shift, int $pair): bool
    {
        if ($pair < 1 || $pair > self::MAX_PAIRS) {
            return false;
        }

        return $pair < 4 || ($shift === Shift::First ? $this->first_fourth_pair_enabled : $this->second_fourth_pair_enabled);
    }
}
