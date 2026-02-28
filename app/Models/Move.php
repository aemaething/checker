<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Move extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'player_number',
        'from_row',
        'from_col',
        'to_row',
        'to_col',
        'captures',
    ];

    protected function casts(): array
    {
        return [
            'captures' => 'array',
            'player_number' => 'integer',
            'from_row' => 'integer',
            'from_col' => 'integer',
            'to_row' => 'integer',
            'to_col' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
