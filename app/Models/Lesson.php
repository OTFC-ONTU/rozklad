<?php

namespace App\Models;

use App\Enums\LessonFormat;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Пара тижневого шаблону. Підгрупи в одній клітинці зберігаються окремими парами. */
class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_version_id', 'weekday', 'pair', 'week', 'discipline_id', 'title',
        'is_fixed_block', 'format', 'online_url', 'is_pinned', 'override_reason',
    ];

    protected $attributes = [
        'week' => 'every',
        'format' => 'in_person',
        'is_fixed_block' => false,
        'is_pinned' => false,
    ];

    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'pair' => 'integer',
            'week' => WeekParity::class,
            'format' => LessonFormat::class,
            'is_fixed_block' => 'boolean',
            'is_pinned' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'schedule_version_id');
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LessonParticipant::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class);
    }

    /** Назва для повідомлень і друку. */
    public function displayTitle(): string
    {
        return $this->discipline?->short_name ?? $this->title ?? '—';
    }
}
