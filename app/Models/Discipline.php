<?php

namespace App\Models;

use App\Enums\RoomType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discipline extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'short_name', 'room_type'];

    protected function casts(): array
    {
        return ['room_type' => RoomType::class];
    }
}
