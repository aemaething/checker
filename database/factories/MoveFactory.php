<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Move>
 */
class MoveFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'player_number' => fake()->numberBetween(1, 2),
            'from_row' => fake()->numberBetween(0, 7),
            'from_col' => fake()->numberBetween(0, 7),
            'to_row' => fake()->numberBetween(0, 7),
            'to_col' => fake()->numberBetween(0, 7),
            'captures' => null,
        ];
    }
}
