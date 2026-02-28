<?php

namespace App\Models;

use App\Enums\GameStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'player1_token',
        'player2_token',
        'board_state',
        'current_turn',
        'status',
        'winner',
    ];

    protected function casts(): array
    {
        return [
            'board_state' => 'array',
            'current_turn' => 'integer',
            'status' => GameStatus::class,
            'winner' => 'integer',
        ];
    }

    public function moves(): HasMany
    {
        return $this->hasMany(Move::class);
    }
}
