<?php

namespace App\Services;

/**
 * German Dame (Draughts) game service.
 *
 * Board layout: flat array of 64 cells indexed by row * 8 + col.
 * Dark square (playable) = (row + col) % 2 === 1.
 * Player 1 starts on rows 5–7 and moves towards row 0.
 * Player 2 starts on rows 0–2 and moves towards row 7.
 *
 * @phpstan-type Cell array{player: int, isKing: bool}|null
 * @phpstan-type Board array{cells: array<int, Cell>}
 * @phpstan-type Move array{from_row: int, from_col: int, to_row: int, to_col: int, captures: array<array{row: int, col: int}>}
 */
class CheckersGameService
{
    public function initialBoard(): array
    {
        $cells = array_fill(0, 64, null);

        for ($row = 0; $row < 8; $row++) {
            for ($col = 0; $col < 8; $col++) {
                if (($row + $col) % 2 !== 1) {
                    continue;
                }

                if ($row <= 2) {
                    $cells[$row * 8 + $col] = ['player' => 2, 'isKing' => false];
                } elseif ($row >= 5) {
                    $cells[$row * 8 + $col] = ['player' => 1, 'isKing' => false];
                }
            }
        }

        return ['cells' => $cells];
    }

    /**
     * Get all valid moves for a specific piece, respecting mandatory capture.
     *
     * When captures are available globally, only capture moves for this piece are returned.
     *
     * @return array<int, Move>
     */
    public function getValidMovesForPiece(array $board, int $row, int $col, int $player): array
    {
        $cell = $board['cells'][$row * 8 + $col] ?? null;

        if ($cell === null || $cell['player'] !== $player) {
            return [];
        }

        $allCaptures = $this->getMandatoryCaptures($board, $player);

        if (! empty($allCaptures)) {
            // Only return captures for this specific piece that match max-capture requirement.
            $maxDepth = $this->maxCaptureDepth($allCaptures);
            $pieceCapturesByMaxDepth = array_filter(
                $allCaptures,
                fn ($m) => $m['from_row'] === $row && $m['from_col'] === $col && count($m['captures']) === $maxDepth
            );

            return array_values($pieceCapturesByMaxDepth);
        }

        return $this->getNonCaptureMoves($board, $row, $col, $player, $cell['isKing']);
    }

    /**
     * Get all valid moves for the given player, enforcing mandatory capture + maximization rule.
     *
     * @return array<int, Move>
     */
    public function getAllValidMoves(array $board, int $player): array
    {
        $captures = $this->getMandatoryCaptures($board, $player);

        if (! empty($captures)) {
            $maxDepth = $this->maxCaptureDepth($captures);

            return array_values(array_filter($captures, fn ($m) => count($m['captures']) === $maxDepth));
        }

        $moves = [];

        for ($row = 0; $row < 8; $row++) {
            for ($col = 0; $col < 8; $col++) {
                $cell = $board['cells'][$row * 8 + $col] ?? null;

                if ($cell === null || $cell['player'] !== $player) {
                    continue;
                }

                $moves = array_merge($moves, $this->getNonCaptureMoves($board, $row, $col, $player, $cell['isKing']));
            }
        }

        return $moves;
    }

    /**
     * Get all mandatory capture sequences for the given player.
     *
     * @return array<int, Move>
     */
    public function getMandatoryCaptures(array $board, int $player): array
    {
        $captures = [];

        for ($row = 0; $row < 8; $row++) {
            for ($col = 0; $col < 8; $col++) {
                $cell = $board['cells'][$row * 8 + $col] ?? null;

                if ($cell === null || $cell['player'] !== $player) {
                    continue;
                }

                $captures = array_merge($captures, $this->getCaptureSequences($board, $row, $col, $player, $cell['isKing'], []));
            }
        }

        return $captures;
    }

    /**
     * Apply a move to the board, handling captures and king promotion.
     *
     * @param  Move  $move
     */
    public function applyMove(array $board, array $move): array
    {
        $cells = $board['cells'];
        $piece = $cells[$move['from_row'] * 8 + $move['from_col']];

        // Remove captured pieces.
        foreach ($move['captures'] as $capture) {
            $cells[$capture['row'] * 8 + $capture['col']] = null;
        }

        // Move the piece.
        $cells[$move['from_row'] * 8 + $move['from_col']] = null;

        $isKing = $piece['isKing'];

        // Promote to king when reaching the last rank (chain ends here per German rules).
        if ($piece['player'] === 1 && $move['to_row'] === 0) {
            $isKing = true;
        } elseif ($piece['player'] === 2 && $move['to_row'] === 7) {
            $isKing = true;
        }

        $cells[$move['to_row'] * 8 + $move['to_col']] = ['player' => $piece['player'], 'isKing' => $isKing];

        return ['cells' => $cells];
    }

    public function isGameOver(array $board, int $nextPlayer): bool
    {
        return empty($this->getAllValidMoves($board, $nextPlayer));
    }

    public function getWinner(array $board, int $lastPlayer): int|null
    {
        $nextPlayer = $lastPlayer === 1 ? 2 : 1;

        if ($this->isGameOver($board, $nextPlayer)) {
            return $lastPlayer;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<array{row: int, col: int}>  $alreadyCaptured
     * @return array<int, Move>
     */
    private function getCaptureSequences(array $board, int $row, int $col, int $player, bool $isKing, array $alreadyCaptured): array
    {
        $sequences = [];

        if ($isKing) {
            $sequences = $this->getKingCaptureSequences($board, $row, $col, $player, $alreadyCaptured);
        } else {
            $sequences = $this->getManCaptureSequences($board, $row, $col, $player, $alreadyCaptured);
        }

        return $sequences;
    }

    /** @param array<array{row: int, col: int}> $alreadyCaptured */
    private function getManCaptureSequences(array $board, int $row, int $col, int $player, array $alreadyCaptured): array
    {
        $opponent = $player === 1 ? 2 : 1;
        $directions = [[-1, -1], [-1, 1], [1, -1], [1, 1]];
        $sequences = [];

        foreach ($directions as [$dr, $dc]) {
            $midRow = $row + $dr;
            $midCol = $col + $dc;
            $landRow = $row + 2 * $dr;
            $landCol = $col + 2 * $dc;

            if (! $this->isOnBoard($midRow, $midCol) || ! $this->isOnBoard($landRow, $landCol)) {
                continue;
            }

            $midCell = $board['cells'][$midRow * 8 + $midCol];
            $landCell = $board['cells'][$landRow * 8 + $landCol];

            if ($midCell === null || $midCell['player'] !== $opponent || $landCell !== null) {
                continue;
            }

            if ($this->isAlreadyCaptured($midRow, $midCol, $alreadyCaptured)) {
                continue;
            }

            $newCaptures = array_merge($alreadyCaptured, [['row' => $midRow, 'col' => $midCol]]);

            // Apply the capture temporarily to find further captures.
            $tempCells = $board['cells'];
            $tempCells[$midRow * 8 + $midCol] = null;
            $tempCells[$row * 8 + $col] = null;
            $tempBoard = ['cells' => $tempCells];

            // Check for promotion: if landing on king row, piece becomes king and chain stops.
            $becomesKing = ($player === 1 && $landRow === 0) || ($player === 2 && $landRow === 7);

            if ($becomesKing) {
                $sequences[] = [
                    'from_row' => $row,
                    'from_col' => $col,
                    'to_row' => $landRow,
                    'to_col' => $landCol,
                    'captures' => $newCaptures,
                ];
                continue;
            }

            $tempCells[$landRow * 8 + $landCol] = ['player' => $player, 'isKing' => false];
            $tempBoard = ['cells' => $tempCells];

            $further = $this->getManCaptureSequences($tempBoard, $landRow, $landCol, $player, $newCaptures);

            if (empty($further)) {
                $sequences[] = [
                    'from_row' => $row,
                    'from_col' => $col,
                    'to_row' => $landRow,
                    'to_col' => $landCol,
                    'captures' => $newCaptures,
                ];
            } else {
                foreach ($further as $seq) {
                    $sequences[] = array_merge($seq, ['from_row' => $row, 'from_col' => $col]);
                }
            }
        }

        return $sequences;
    }

    /** @param array<array{row: int, col: int}> $alreadyCaptured */
    private function getKingCaptureSequences(array $board, int $row, int $col, int $player, array $alreadyCaptured): array
    {
        $opponent = $player === 1 ? 2 : 1;
        $directions = [[-1, -1], [-1, 1], [1, -1], [1, 1]];
        $sequences = [];

        foreach ($directions as [$dr, $dc]) {
            $r = $row + $dr;
            $c = $col + $dc;
            $foundOpponent = null;

            // Slide until we hit a piece or edge.
            while ($this->isOnBoard($r, $c)) {
                $cell = $board['cells'][$r * 8 + $c];

                if ($cell !== null) {
                    if ($cell['player'] === $opponent && ! $this->isAlreadyCaptured($r, $c, $alreadyCaptured)) {
                        $foundOpponent = ['row' => $r, 'col' => $c];
                    }
                    break;
                }

                $r += $dr;
                $c += $dc;
            }

            if ($foundOpponent === null) {
                continue;
            }

            // Land on any empty square beyond the captured piece.
            $landR = $foundOpponent['row'] + $dr;
            $landC = $foundOpponent['col'] + $dc;

            while ($this->isOnBoard($landR, $landC) && $board['cells'][$landR * 8 + $landC] === null) {
                $newCaptures = array_merge($alreadyCaptured, [$foundOpponent]);

                $tempCells = $board['cells'];
                $tempCells[$foundOpponent['row'] * 8 + $foundOpponent['col']] = null;
                $tempCells[$row * 8 + $col] = null;
                $tempCells[$landR * 8 + $landC] = ['player' => $player, 'isKing' => true];
                $tempBoard = ['cells' => $tempCells];

                $further = $this->getKingCaptureSequences($tempBoard, $landR, $landC, $player, $newCaptures);

                if (empty($further)) {
                    $sequences[] = [
                        'from_row' => $row,
                        'from_col' => $col,
                        'to_row' => $landR,
                        'to_col' => $landC,
                        'captures' => $newCaptures,
                    ];
                } else {
                    foreach ($further as $seq) {
                        $sequences[] = array_merge($seq, ['from_row' => $row, 'from_col' => $col]);
                    }
                }

                $landR += $dr;
                $landC += $dc;
            }
        }

        return $sequences;
    }

    /** @return array<int, Move> */
    private function getNonCaptureMoves(array $board, int $row, int $col, int $player, bool $isKing): array
    {
        $moves = [];

        if ($isKing) {
            $directions = [[-1, -1], [-1, 1], [1, -1], [1, 1]];

            foreach ($directions as [$dr, $dc]) {
                $r = $row + $dr;
                $c = $col + $dc;

                while ($this->isOnBoard($r, $c) && $board['cells'][$r * 8 + $c] === null) {
                    $moves[] = [
                        'from_row' => $row,
                        'from_col' => $col,
                        'to_row' => $r,
                        'to_col' => $c,
                        'captures' => [],
                    ];
                    $r += $dr;
                    $c += $dc;
                }
            }
        } else {
            // Men move diagonally forward only.
            $forwardDir = $player === 1 ? -1 : 1;
            $directions = [[$forwardDir, -1], [$forwardDir, 1]];

            foreach ($directions as [$dr, $dc]) {
                $newRow = $row + $dr;
                $newCol = $col + $dc;

                if ($this->isOnBoard($newRow, $newCol) && $board['cells'][$newRow * 8 + $newCol] === null) {
                    $moves[] = [
                        'from_row' => $row,
                        'from_col' => $col,
                        'to_row' => $newRow,
                        'to_col' => $newCol,
                        'captures' => [],
                    ];
                }
            }
        }

        return $moves;
    }

    private function isOnBoard(int $row, int $col): bool
    {
        return $row >= 0 && $row < 8 && $col >= 0 && $col < 8;
    }

    /** @param array<array{row: int, col: int}> $captured */
    private function isAlreadyCaptured(int $row, int $col, array $captured): bool
    {
        foreach ($captured as $c) {
            if ($c['row'] === $row && $c['col'] === $col) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, Move> $captures */
    private function maxCaptureDepth(array $captures): int
    {
        return max(array_map(fn ($m) => count($m['captures']), $captures));
    }
}
