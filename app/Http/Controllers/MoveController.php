<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Events\MoveMade;
use App\Http\Requests\StoreMoveRequest;
use App\Models\Game;
use App\Services\CheckersGameService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MoveController extends Controller
{
    public function store(StoreMoveRequest $request, string $playerToken, CheckersGameService $service): JsonResponse
    {
        $game = Game::query()
            ->where('player1_token', $playerToken)
            ->orWhere('player2_token', $playerToken)
            ->firstOrFail();

        if ($game->status !== GameStatus::Active) {
            return response()->json(['message' => 'Game is not active.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $playerNumber = $game->player1_token === $playerToken ? 1 : 2;

        if ($game->current_turn !== $playerNumber) {
            return response()->json(['message' => 'It is not your turn.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validated();
        $fromRow = (int) $validated['from_row'];
        $fromCol = (int) $validated['from_col'];
        $toRow = (int) $validated['to_row'];
        $toCol = (int) $validated['to_col'];

        $validMoves = $service->getAllValidMoves($game->board_state, $playerNumber);

        $matchingMove = null;

        foreach ($validMoves as $validMove) {
            if (
                $validMove['from_row'] === $fromRow &&
                $validMove['from_col'] === $fromCol &&
                $validMove['to_row'] === $toRow &&
                $validMove['to_col'] === $toCol
            ) {
                $matchingMove = $validMove;
                break;
            }
        }

        if ($matchingMove === null) {
            return response()->json(['message' => 'Invalid move.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $newBoardState = $service->applyMove($game->board_state, $matchingMove);
        $nextTurn = $playerNumber === 1 ? 2 : 1;
        $winner = $service->getWinner($newBoardState, $playerNumber);
        $newStatus = $winner !== null ? GameStatus::Finished : GameStatus::Active;

        $game->update([
            'board_state' => $newBoardState,
            'current_turn' => $nextTurn,
            'status' => $newStatus,
            'winner' => $winner,
        ]);

        $move = $game->moves()->create([
            'player_number' => $playerNumber,
            'from_row' => $fromRow,
            'from_col' => $fromCol,
            'to_row' => $toRow,
            'to_col' => $toCol,
            'captures' => $matchingMove['captures'],
        ]);

        try {
            broadcast(new MoveMade($game, $move))->toOthers();
        } catch (\Throwable) {
            // Reverb may not be running; broadcasting failure must not abort the move.
        }

        return response()->json([
            'board_state' => $newBoardState,
            'current_turn' => $nextTurn,
            'status' => $newStatus->value,
            'winner' => $winner,
        ]);
    }
}
