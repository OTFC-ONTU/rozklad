<?php

namespace App\Models;

use App\Enums\GroupActivityKind;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Період графіка освітнього процесу групи (практика, сесія, канікули…). */
class GroupActivity extends Model
{
    protected $fillable = ['student_group_id', 'kind', 'practice_type_id', 'starts_on', 'ends_on', 'note'];

    protected function casts(): array
    {
        return [
            'kind' => GroupActivityKind::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $activity) {
            if ($activity->kind !== GroupActivityKind::Practice) {
                $activity->practice_type_id = null;
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }

    public function practiceType(): BelongsTo
    {
        return $this->belongsTo(PracticeType::class);
    }

    public function covers(CarbonInterface $date): bool
    {
        return $date->betweenIncluded($this->starts_on, $this->ends_on);
    }

    public function label(): string
    {
        return $this->practiceType?->name ?? $this->kind->label();
    }

    public function color(): string
    {
        return $this->practiceType?->color ?? $this->kind->hex();
    }
}
