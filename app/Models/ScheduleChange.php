<?php

namespace App\Models;

use App\Enums\LessonFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Заміна на конкретну дату: скасування чи зміна пари шаблону або додаткова пара.
 * Зберігає повний стан пари на цей день, а не різницю з шаблоном.
 */
class ScheduleChange extends Model
{
    protected $fillable = [
        'date', 'lesson_id', 'is_cancelled', 'pair', 'discipline_id', 'title',
        'format', 'online_url', 'reason', 'override_reason', 'user_id',
    ];

    protected $attributes = [
        'is_cancelled' => false,
        'format' => 'in_person',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'is_cancelled' => 'boolean',
            'pair' => 'integer',
            'format' => LessonFormat::class,
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ScheduleChangeParticipant::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Додаткова пара, якої немає в шаблоні. */
    public function isAddition(): bool
    {
        return $this->lesson_id === null;
    }
}
