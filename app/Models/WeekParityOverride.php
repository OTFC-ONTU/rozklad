<?php

namespace App\Models;

use App\Enums\WeekParity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeekParityOverride extends Model
{
    protected $fillable = ['semester_id', 'week_start', 'parity', 'note'];

    protected function casts(): array
    {
        return [
            'week_start' => 'immutable_date',
            'parity' => WeekParity::class,
        ];
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /** Будь-яку дату тижня зберігаємо як його понеділок. */
    protected function weekStart(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : CarbonImmutable::parse($value)->startOfWeek()->toDateString(),
        );
    }
}
