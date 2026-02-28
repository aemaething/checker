<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Services\CheckersGameService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'player1_token' => Str::uuid(),
            'player2_token' => Str::uuid(),
            'board_state' => app(CheckersGameService::class)->initialBoard(),
            'current_turn' => 1,
            'status' => GameStatus::Waiting,
            'winner' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => GameStatus::Active]);
    }

    public function finished(int $winner = 1): static
    {
        return $this->state(['status' => GameStatus::Finished, 'winner' => $winner]);
    }
}
