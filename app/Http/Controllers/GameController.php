<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Services\CheckersGameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('GameCreate');
    }

    public function store(CheckersGameService $service): RedirectResponse
    {
        $game = Game::create([
            'uuid' => Str::uuid(),
            'player1_token' => Str::uuid(),
            'player2_token' => Str::uuid(),
            'board_state' => $service->initialBoard(),
            'current_turn' => 1,
            'status' => GameStatus::Waiting,
        ]);

        return redirect()->route('game.show', ['playerToken' => $game->player1_token]);
    }

    public function show(string $playerToken): Response
    {
        $game = Game::query()
            ->where('player1_token', $playerToken)
            ->orWhere('player2_token', $playerToken)
            ->firstOrFail();

        $playerNumber = $game->player1_token === $playerToken ? 1 : 2;

        // Player 2 joining activates the game.
        if ($playerNumber === 2 && $game->status === GameStatus::Waiting) {
            $game->update(['status' => GameStatus::Active]);
            $game->refresh();
        }

        $shareUrl = null;

        if ($playerNumber === 1) {
            $shareUrl = route('game.show', ['playerToken' => $game->player2_token]);
        }

        return Inertia::render('Game', [
            'game' => [
                'uuid' => $game->uuid,
                'status' => $game->status->value,
                'current_turn' => $game->current_turn,
                'winner' => $game->winner,
                'board_state' => $game->board_state,
                'player_number' => $playerNumber,
                'share_url' => $shareUrl,
            ],
        ]);
    }
}
