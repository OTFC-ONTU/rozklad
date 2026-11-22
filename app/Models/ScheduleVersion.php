<?php

namespace App\Models;

use App\Enums\ScheduleVersionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** Версія тижневого шаблону розкладу «діє з». */
class ScheduleVersion extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'effective_from', 'status', 'published_at'];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'status' => ScheduleVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /** Опублікована версія, що діє на дату. */
    public static function effectiveOn(CarbonInterface $date): ?self
    {
        return static::query()
            ->where('status', ScheduleVersionStatus::Published)
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }

    public function isDraft(): bool
    {
        return $this->status === ScheduleVersionStatus::Draft;
    }

    public function publish(): void
    {
        $this->update(['status' => ScheduleVersionStatus::Published, 'published_at' => now()]);
    }

    /** Нова чернетка з усіма парами цієї версії — основа для наступного «діє з». */
    public function duplicate(string $title, CarbonInterface|string $effectiveFrom): self
    {
        return DB::transaction(function () use ($title, $effectiveFrom) {
            $copy = static::create(['title' => $title, 'effective_from' => $effectiveFrom]);

            $this->lessons()->with(['participants', 'teachers', 'rooms'])->each(function (Lesson $lesson) use ($copy) {
                $new = $lesson->replicate()->fill(['schedule_version_id' => $copy->id]);
                $new->save();
                $new->participants()->createMany(
                    $lesson->participants->map->only(['student_group_id', 'subgroup_id'])->all()
                );
                $new->teachers()->sync($lesson->teachers->modelKeys());
                $new->rooms()->sync($lesson->rooms->modelKeys());
            });

            return $copy;
        });
    }
}
