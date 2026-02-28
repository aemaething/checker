<?php

namespace Tests\Feature\Game;

use App\Enums\GameStatus;
use App\Events\MoveMade;
use App\Models\Game;
use App\Services\CheckersGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameMoveTest extends TestCase
{
    use RefreshDatabase;

    private function postMove(Game $game, int $playerNumber, array $move): \Illuminate\Testing\TestResponse
    {
        $token = $playerNumber === 1 ? $game->player1_token : $game->player2_token;

        return $this->postJson("/game/{$token}/moves", $move);
    }

    public function test_valid_move_saves_board_and_move_record(): void
    {
        Event::fake();

        $game = Game::factory()->active()->create();

        // Player 1 moves from row 5, col 0 to row 4, col 1 (standard first move).
        $response = $this->postMove($game, 1, [
            'from_row' => 5,
            'from_col' => 0,
            'to_row' => 4,
            'to_col' => 1,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('moves', [
            'game_id' => $game->id,
            'player_number' => 1,
            'from_row' => 5,
            'from_col' => 0,
            'to_row' => 4,
            'to_col' => 1,
        ]);

        $game->refresh();
        $landingCell = $game->board_state['cells'][4 * 8 + 1];
        $this->assertNotNull($landingCell);
        $this->assertSame(1, $landingCell['player']);
    }

    public function test_valid_move_switches_current_turn(): void
    {
        Event::fake();

        $game = Game::factory()->active()->create(['current_turn' => 1]);

        $this->postMove($game, 1, [
            'from_row' => 5,
            'from_col' => 0,
            'to_row' => 4,
            'to_col' => 1,
        ]);

        $this->assertDatabaseHas('games', ['id' => $game->id, 'current_turn' => 2]);
    }

    public function test_move_on_wrong_turn_returns_422(): void
    {
        $game = Game::factory()->active()->create(['current_turn' => 1]);

        // Player 2 tries to move when it is player 1's turn.
        $response = $this->postMove($game, 2, [
            'from_row' => 2,
            'from_col' => 1,
            'to_row' => 3,
            'to_col' => 0,
        ]);

        $response->assertUnprocessable();
    }

    public function test_invalid_move_returns_422(): void
    {
        $game = Game::factory()->active()->create(['current_turn' => 1]);

        // Moving backwards without being a king → invalid.
        $response = $this->postMove($game, 1, [
            'from_row' => 5,
            'from_col' => 0,
            'to_row' => 6,
            'to_col' => 1,
        ]);

        $response->assertUnprocessable();
    }

    public function test_mandatory_capture_is_enforced_by_api(): void
    {
        Event::fake();

        $service = new CheckersGameService;
        $board = ['cells' => array_fill(0, 64, null)];
        // P1 man at row 5, col 4.
        $board['cells'][5 * 8 + 4] = ['player' => 1, 'isKing' => false];
        // P2 man adjacent — capture available (row 4, col 5 → land row 3, col 6).
        $board['cells'][4 * 8 + 5] = ['player' => 2, 'isKing' => false];
        // P1 has another piece that could move normally.
        $board['cells'][5 * 8 + 0] = ['player' => 1, 'isKing' => false];

        $game = Game::factory()->active()->create([
            'board_state' => $board,
            'current_turn' => 1,
        ]);

        // Attempt a non-capture move with the other piece.
        $response = $this->postMove($game, 1, [
            'from_row' => 5,
            'from_col' => 0,
            'to_row' => 4,
            'to_col' => 1,
        ]);

        $response->assertUnprocessable();
    }

    public function test_move_made_event_is_dispatched(): void
    {
        Event::fake();

        $game = Game::factory()->active()->create(['current_turn' => 1]);

        $this->postMove($game, 1, [
            'from_row' => 5,
            'from_col' => 0,
            'to_row' => 4,
            'to_col' => 1,
        ]);

        Event::assertDispatched(MoveMade::class);
    }

    public function test_game_ends_and_winner_is_set_when_opponent_has_no_moves(): void
    {
        Event::fake();

        // Board where P1 is about to capture P2's last piece.
        $board = ['cells' => array_fill(0, 64, null)];
        // P1 man can jump over last P2 piece.
        $board['cells'][2 * 8 + 1] = ['player' => 1, 'isKing' => false];
        $board['cells'][1 * 8 + 2] = ['player' => 2, 'isKing' => false];
        // Landing square is row 0, col 3 (also promotion square).

        $game = Game::factory()->active()->create([
            'board_state' => $board,
            'current_turn' => 1,
        ]);

        $this->postMove($game, 1, [
            'from_row' => 2,
            'from_col' => 1,
            'to_row' => 0,
            'to_col' => 3,
        ]);

        $game->refresh();
        $this->assertSame(GameStatus::Finished, $game->status);
        $this->assertSame(1, $game->winner);
    }

    public function test_move_is_rejected_in_finished_game(): void
    {
        $game = Game::factory()->finished()->create(['current_turn' => 1]);

        $response = $this->postMove($game, 1, [
            'from_row' => 5,
            'from_col' => 0,
            'to_row' => 4,
            'to_col' => 1,
        ]);

        $response->assertUnprocessable();
    }
}
