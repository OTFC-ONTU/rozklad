<?php

namespace App\Models;

use App\Enums\RoomType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasFactory;

    protected $fillable = ['building_id', 'number', 'type', 'capacity', 'max_groups'];

    protected function casts(): array
    {
        return [
            'type' => RoomType::class,
            'capacity' => 'integer',
            'max_groups' => 'integer',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** Позначення як у паперовому розкладі: «322-2», для спортзалу «с/з». */
    public function label(): string
    {
        if ($this->type === RoomType::Gym) {
            return 'с/з';
        }

        return "{$this->number}-{$this->building->number}";
    }
}
