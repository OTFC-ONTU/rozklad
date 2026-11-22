<?php

namespace App\Models;

use App\Enums\Shift;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentGroup extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'course', 'students_count', 'shift'];

    protected function casts(): array
    {
        return [
            'course' => 'integer',
            'students_count' => 'integer',
            'shift' => Shift::class,
        ];
    }

    public function subgroups(): HasMany
    {
        return $this->hasMany(Subgroup::class);
    }

    public function shiftChanges(): HasMany
    {
        return $this->hasMany(StudentGroupShift::class);
    }

    /** Зміна групи на дату: останній запис «з дати», інакше зміна за замовчуванням. */
    public function shiftOn(CarbonInterface $date): Shift
    {
        $change = $this->shiftChanges
            ->filter(fn (StudentGroupShift $c) => $c->effective_from->lte($date))
            ->sortByDesc('effective_from')
            ->first();

        return $change?->shift ?? $this->shift;
    }
}
