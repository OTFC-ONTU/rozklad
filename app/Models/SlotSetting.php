<?php

namespace App\Models;

use App\Enums\Shift;
use App\Enums\Weekday;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Які слоти доступні зміні з певної дати. Пн–Пт і пари 1–3 доступні завжди,
 * субота і 4-а пара — за перемикачами. 5-ї пари не буває.
 */
class SlotSetting extends Model
{
    public const int MAX_PAIRS = 4;

    protected $fillable = ['shift', 'effective_from', 'saturday_enabled', 'fourth_pair_enabled'];

    protected $attributes = [
        'saturday_enabled' => false,
        'fourth_pair_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'shift' => Shift::class,
            'effective_from' => 'date',
            'saturday_enabled' => 'boolean',
            'fourth_pair_enabled' => 'boolean',
        ];
    }

    /** Налаштування, що діють для зміни на дату; якщо записів немає — типові. */
    public static function for(Shift $shift, CarbonInterface $date): self
    {
        return static::query()
            ->where('shift', $shift)
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first()
            ?? new static(['shift' => $shift]);
    }

    public function allows(Weekday $weekday, int $pair): bool
    {
        if ($pair < 1 || $pair > self::MAX_PAIRS) {
            return false;
        }

        if ($weekday === Weekday::Saturday && ! $this->saturday_enabled) {
            return false;
        }

        return $pair < 4 || $this->fourth_pair_enabled;
    }
}
