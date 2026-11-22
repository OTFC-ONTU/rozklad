<?php

namespace App\Models;

use App\Enums\CalendarDayKind;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use Illuminate\Database\Eloquent\Model;

class CalendarDay extends Model
{
    protected $fillable = ['date', 'kind', 'follows_weekday', 'follows_parity', 'note'];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'kind' => CalendarDayKind::class,
            'follows_weekday' => Weekday::class,
            'follows_parity' => WeekParity::class,
        ];
    }
}
