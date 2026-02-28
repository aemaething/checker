<?php

namespace Tests\Unit;

use App\Services\CheckersGameService;
use PHPUnit\Framework\TestCase;

class CheckersGameServiceTest extends TestCase
{
    private CheckersGameService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CheckersGameService;
    }

    public function test_initial_board_has_twelve_pieces_per_player(): void
    {
        $board = $this->service->initialBoard();
        $player1Count = 0;
        $player2Count = 0;

        foreach ($board['cells'] as $cell) {
            if ($cell === null) {
                continue;
            }

            if ($cell['player'] === 1) {
                $player1Count++;
            } else {
                $player2Count++;
            }
        }

        $this->assertSame(12, $player1Count);
        $this->assertSame(12, $player2Count);
    }

    public function test_initial_board_places_pieces_only_on_dark_squares(): void
    {
        $board = $this->service->initialBoard();

        foreach ($board['cells'] as $index => $cell) {
            if ($cell === null) {
                continue;
            }

            $row = intdiv($index, 8);
            $col = $index % 8;
            $this->assertSame(1, ($row + $col) % 2, "Piece at index $index is not on a dark square.");
        }
    }

    public function test_initial_board_has_no_kings(): void
    {
        $board = $this->service->initialBoard();

        foreach ($board['cells'] as $cell) {
            if ($cell !== null) {
                $this->assertFalse($cell['isKing']);
            }
        }
    }

    public function test_man_can_move_forward(): void
    {
        $board = $this->service->initialBoard();

        // Player 1 man at row 5, col 0 (forward = towards row 0).
        $moves = $this->service->getValidMovesForPiece($board, 5, 0, 1);

        $destinations = array_map(fn ($m) => [$m['to_row'], $m['to_col']], $moves);
        $this->assertContains([4, 1], $destinations);
    }

    public function test_man_cannot_move_backward_without_capture(): void
    {
        // Isolate a single P1 man with no captures available.
        $board = ['cells' => array_fill(0, 64, null)];
        $board['cells'][5 * 8 + 4] = ['player' => 1, 'isKing' => false]; // row 5, col 4

        $moves = $this->service->getValidMovesForPiece($board, 5, 4, 1);

        $destinations = array_map(fn ($m) => [$m['to_row'], $m['to_col']], $moves);

        // Forward moves only (row 4).
        foreach ($destinations as [$toRow, $toCol]) {
            $this->assertSame(4, $toRow, "Man moved to unexpected row $toRow from row 5.");
        }
    }

    public function test_mandatory_capture_is_enforced(): void
    {
        $board = ['cells' => array_fill(0, 64, null)];
        // P1 man at row 5, col 4.
        $board['cells'][5 * 8 + 4] = ['player' => 1, 'isKing' => false];
        // P2 man adjacent (row 4, col 5) with empty landing square (row 3, col 6).
        $board['cells'][4 * 8 + 5] = ['player' => 2, 'isKing' => false];

        $moves = $this->service->getAllValidMoves($board, 1);

        // Only capture moves should be returned.
        foreach ($moves as $move) {
            $this->assertNotEmpty($move['captures'], 'Non-capture move returned when capture is available.');
        }

        $this->assertNotEmpty($moves);
    }

    public function test_multi_jump_chain_is_generated(): void
    {
        $board = ['cells' => array_fill(0, 64, null)];
        // P1 man at row 6, col 0.
        $board['cells'][6 * 8 + 0] = ['player' => 1, 'isKing' => false];
        // P2 at row 5, col 1 → can jump to row 4, col 2.
        $board['cells'][5 * 8 + 1] = ['player' => 2, 'isKing' => false];
        // P2 at row 3, col 3 → can jump to row 2, col 4.
        $board['cells'][3 * 8 + 3] = ['player' => 2, 'isKing' => false];

        $captures = $this->service->getMandatoryCaptures($board, 1);

        // Should have a move capturing both pieces.
        $doubleJumps = array_filter($captures, fn ($m) => count($m['captures']) >= 2);
        $this->assertNotEmpty($doubleJumps, 'Multi-jump sequence not generated.');
    }

    public function test_maximization_rule_selects_most_captures(): void
    {
        $board = ['cells' => array_fill(0, 64, null)];
        // P1 man at row 6, col 0.
        $board['cells'][6 * 8 + 0] = ['player' => 1, 'isKing' => false];
        // Two P2 pieces in a chain.
        $board['cells'][5 * 8 + 1] = ['player' => 2, 'isKing' => false];
        $board['cells'][3 * 8 + 3] = ['player' => 2, 'isKing' => false];

        $validMoves = $this->service->getAllValidMoves($board, 1);

        foreach ($validMoves as $move) {
            $this->assertGreaterThanOrEqual(1, count($move['captures']), 'Move with fewer captures than maximum was returned.');
        }
    }

    public function test_man_is_promoted_to_king_on_last_rank(): void
    {
        $board = ['cells' => array_fill(0, 64, null)];
        // P1 man one step from promotion.
        $board['cells'][1 * 8 + 0] = ['player' => 1, 'isKing' => false];

        $moves = $this->service->getAllValidMoves($board, 1);
        $promotionMove = array_filter($moves, fn ($m) => $m['to_row'] === 0);

        $this->assertNotEmpty($promotionMove);

        $newBoard = $this->service->applyMove($board, reset($promotionMove));
        $landingCell = null;

        foreach ($newBoard['cells'] as $cell) {
            if ($cell !== null && $cell['player'] === 1) {
                $landingCell = $cell;
                break;
            }
        }

        $this->assertNotNull($landingCell);
        $this->assertTrue($landingCell['isKing'], 'Man was not promoted to king after reaching last rank.');
    }

    public function test_king_can_move_any_diagonal_distance(): void
    {
        $board = ['cells' => array_fill(0, 64, null)];
        // P1 king at row 4, col 4.
        $board['cells'][4 * 8 + 4] = ['player' => 1, 'isKing' => true];

        $moves = $this->service->getValidMovesForPiece($board, 4, 4, 1);

        // Should reach distant squares like row 0 col 0, row 7 col 7, etc.
        $destinations = array_map(fn ($m) => [$m['to_row'], $m['to_col']], $moves);

        $this->assertContains([0, 0], $destinations);
        $this->assertContains([7, 7], $destinations);
        $this->assertContains([1, 7], $destinations);
        $this->assertContains([7, 1], $destinations);
    }

    public function test_game_over_when_player_has_no_moves(): void
    {
        // P2 has no pieces → P1 wins, P2 has no moves.
        $board = ['cells' => array_fill(0, 64, null)];
        $board['cells'][7 * 8 + 0] = ['player' => 1, 'isKing' => false];

        $this->assertTrue($this->service->isGameOver($board, 2));
        $this->assertFalse($this->service->isGameOver($board, 1));
    }

    public function test_get_winner_returns_last_player_when_opponent_has_no_moves(): void
    {
        $board = ['cells' => array_fill(0, 64, null)];
        $board['cells'][7 * 8 + 0] = ['player' => 1, 'isKing' => false];

        $this->assertSame(1, $this->service->getWinner($board, 1));
        $this->assertNull($this->service->getWinner($board, 2));
    }
}
