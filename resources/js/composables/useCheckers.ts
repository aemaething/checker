import { store as storeMoveRoute } from '@/actions/App/Http/Controllers/MoveController';
import type { BoardState, Game, MoveMadeEvent, ValidMove } from '@/types/game';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Cell = { row: number; col: number };

export function useCheckers(game: { value: Game }) {
    const selectedCell = ref<Cell | null>(null);
    const validMoves = ref<ValidMove[]>([]);
    const isSubmitting = ref(false);
    const moveError = ref<string | null>(null);

    /**
     * When the user is mid-chain (has clicked piece + first intermediate square),
     * `jumpChains` holds the subset of ValidMoves still matching, and
     * `jumpPath` tracks how many steps have been confirmed so far.
     */
    const jumpChains = ref<ValidMove[]>([]);
    const jumpPathLength = ref(0);

    const isMyTurn = computed(() => game.value.status === 'active' && game.value.current_turn === game.value.player_number);

    function getValidMovesForSelected(): ValidMove[] {
        if (!selectedCell.value) {
            return [];
        }

        return (validMoves.value as ValidMove[]).filter(
            (m) => m.from_row === selectedCell.value!.row && m.from_col === selectedCell.value!.col,
        );
    }

    /**
     * Always shows the *next* clickable square(s):
     * - Chain in progress: `path[jumpPathLength]` of every still-valid chain move.
     * - Initial selection: `path[0]` of every valid move (first step, which for single-step
     *   moves equals the final destination, and for multi-jumps equals the first landing square).
     */
    const highlightedCells = computed<Cell[]>(() => {
        const pathIndex = jumpChains.value.length > 0 ? jumpPathLength.value : 0;
        const moves = jumpChains.value.length > 0 ? jumpChains.value : getValidMovesForSelected();

        const seen = new Set<string>();
        return moves
            .map((m) => m.path[pathIndex])
            .filter((c): c is Cell => {
                if (!c) return false;
                const key = `${c.row},${c.col}`;
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            });
    });

    function reset(): void {
        selectedCell.value = null;
        validMoves.value = [];
        jumpChains.value = [];
        jumpPathLength.value = 0;
    }

    function selectPiece(row: number, col: number): void {
        if (!isMyTurn.value) {
            return;
        }

        // ── Mid-chain click ────────────────────────────────────────────
        if (jumpChains.value.length > 0) {
            const nextStep = jumpPathLength.value;

            // Check if the clicked cell is one of the valid next steps.
            const matchingChains = jumpChains.value.filter(
                (m) => m.path[nextStep]?.row === row && m.path[nextStep]?.col === col,
            );

            if (matchingChains.length === 0) {
                // Clicked somewhere else — cancel the chain.
                reset();
                return;
            }

            const newPathLength = nextStep + 1;

            // Check if this step reaches the final destination of some chains.
            const completedChains = matchingChains.filter((m) => m.path.length === newPathLength);

            if (completedChains.length === 1) {
                // Exactly one chain completed → execute it.
                void executeMove(completedChains[0].to_row, completedChains[0].to_col, completedChains[0]);
                return;
            }

            if (completedChains.length > 1) {
                // Multiple chains reach the same destination with the same number of steps:
                // pick the one with the most captures (should be the same by maximization, but be safe).
                const best = completedChains.reduce((a, b) => (a.captures.length >= b.captures.length ? a : b));
                void executeMove(best.to_row, best.to_col, best);
                return;
            }

            // Still more steps to go — narrow the active chains.
            jumpChains.value = matchingChains;
            jumpPathLength.value = newPathLength;
            return;
        }

        // ── Normal click (no chain in progress) ────────────────────────
        const board = game.value.board_state;
        const cell = board.cells[row * 8 + col];

        if (!cell || cell.player !== game.value.player_number) {
            // Clicking a highlighted target cell (path[0] of some valid moves)?
            if (selectedCell.value && highlightedCells.value.some((c) => c.row === row && c.col === col)) {
                // Filter by which moves have path[0] matching the clicked cell.
                const movesForTarget = getValidMovesForSelected().filter(
                    (m) => m.path[0]?.row === row && m.path[0]?.col === col,
                );

                if (movesForTarget.length === 0) {
                    reset();
                    return;
                }

                if (movesForTarget.every((m) => m.path.length === 1)) {
                    // All matching moves are single-step — pick the one with most captures (or only one).
                    const best = movesForTarget.reduce((a, b) => (a.captures.length >= b.captures.length ? a : b));
                    void executeMove(best.to_row, best.to_col, best);
                } else {
                    // Multi-step chain — start chain navigation at step 1.
                    jumpChains.value = movesForTarget;
                    jumpPathLength.value = 1;
                }
            } else {
                reset();
            }

            return;
        }

        // Own piece clicked → select it.
        selectedCell.value = { row, col };
        validMoves.value = computeValidMoves(board, row, col, game.value.player_number);
        jumpChains.value = [];
        jumpPathLength.value = 0;
    }

    async function executeMove(toRow: number, toCol: number, move: ValidMove): Promise<void> {
        if (!selectedCell.value || isSubmitting.value) {
            return;
        }

        const from = selectedCell.value;
        isSubmitting.value = true;
        reset();

        const playerToken = getPlayerToken();

        moveError.value = null;

        try {
            const response = await fetch(storeMoveRoute(playerToken).url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    from_row: from.row,
                    from_col: from.col,
                    to_row: toRow,
                    to_col: toCol,
                }),
            });

            if (response.ok) {
                const data = (await response.json()) as {
                    board_state: BoardState;
                    current_turn: 1 | 2;
                    status: 'waiting' | 'active' | 'finished';
                    winner: 1 | 2 | null;
                };

                game.value.board_state = data.board_state;
                game.value.current_turn = data.current_turn;
                game.value.status = data.status;
                game.value.winner = data.winner;

                // Sync Inertia page props in background.
                router.reload({ only: ['game'] });
            } else {
                const data = (await response.json().catch(() => ({}))) as { message?: string };
                moveError.value = data.message ?? `Fehler ${response.status}`;
            }
        } catch {
            moveError.value = 'Netzwerkfehler – bitte erneut versuchen.';
        } finally {
            isSubmitting.value = false;
        }
    }

    function applyBroadcastedMove(event: MoveMadeEvent): void {
        game.value.board_state = event.board_state;
        game.value.current_turn = event.current_turn;
        game.value.status = event.status;
        game.value.winner = event.winner;
        reset();
    }

    return {
        selectedCell,
        validMoves,
        highlightedCells,
        isMyTurn,
        isSubmitting,
        moveError,
        selectPiece,
        applyBroadcastedMove,
    };
}

/**
 * Returns valid moves for a specific piece, respecting the global mandatory-capture rule.
 * If any piece of `player` can capture, only those captures are shown (the server enforces
 * the maximization rule for multi-jumps).
 */
function computeValidMoves(board: BoardState, row: number, col: number, player: 1 | 2): ValidMove[] {
    const piece = board.cells[row * 8 + col];

    if (!piece) {
        return [];
    }

    // Check global mandatory captures first.
    const globalCaptures = getAllCapturesForPlayer(board, player);

    if (globalCaptures.length > 0) {
        // Only return captures for THIS piece — if it has none, no moves are available.
        return globalCaptures.filter((m) => m.from_row === row && m.from_col === col);
    }

    return getNonCaptureMoves(board, row, col, player, piece.isKing);
}

function getNonCaptureMoves(board: BoardState, row: number, col: number, player: 1 | 2, isKing: boolean): ValidMove[] {
    const moves: ValidMove[] = [];
    const forwardDir = player === 1 ? -1 : 1;
    const rowDirs = isKing ? [-1, 1] : [forwardDir];

    for (const dr of rowDirs) {
        for (const dc of [-1, 1]) {
            if (isKing) {
                let r = row + dr;
                let c = col + dc;

                while (r >= 0 && r < 8 && c >= 0 && c < 8) {
                    if (board.cells[r * 8 + c] !== null) {
                        break;
                    }

                    moves.push({ from_row: row, from_col: col, to_row: r, to_col: c, captures: [], path: [{ row: r, col: c }] });
                    r += dr;
                    c += dc;
                }
            } else {
                const nr = row + dr;
                const nc = col + dc;

                if (nr >= 0 && nr < 8 && nc >= 0 && nc < 8 && board.cells[nr * 8 + nc] === null) {
                    moves.push({ from_row: row, from_col: col, to_row: nr, to_col: nc, captures: [], path: [{ row: nr, col: nc }] });
                }
            }
        }
    }

    return moves;
}

/**
 * Returns all capture sequences for every piece of `player`, with full multi-jump chains
 * and the maximization rule applied — mirroring the PHP CheckersGameService logic.
 */
function getAllCapturesForPlayer(board: BoardState, player: 1 | 2): ValidMove[] {
    const captures: ValidMove[] = [];

    for (let r = 0; r < 8; r++) {
        for (let c = 0; c < 8; c++) {
            const cell = board.cells[r * 8 + c];

            if (!cell || cell.player !== player) {
                continue;
            }

            const seqs = cell.isKing
                ? getKingCaptureSequences(board, r, c, player, [], [])
                : getManCaptureSequences(board, r, c, player, [], []);

            captures.push(...seqs);
        }
    }

    if (captures.length === 0) {
        return [];
    }

    // Maximization rule: only keep sequences with the most captures.
    const maxDepth = Math.max(...captures.map((m) => m.captures.length));

    return captures.filter((m) => m.captures.length === maxDepth);
}

function getManCaptureSequences(
    board: BoardState,
    row: number,
    col: number,
    player: 1 | 2,
    alreadyCaptured: Cell[],
    pathSoFar: Cell[],
): ValidMove[] {
    const opponent = player === 1 ? 2 : 1;
    const dirs: [number, number][] = [[-1, -1], [-1, 1], [1, -1], [1, 1]];
    const sequences: ValidMove[] = [];

    for (const [dr, dc] of dirs) {
        const midRow = row + dr;
        const midCol = col + dc;
        const landRow = row + 2 * dr;
        const landCol = col + 2 * dc;

        if (landRow < 0 || landRow >= 8 || landCol < 0 || landCol >= 8) {
            continue;
        }

        const midCell = board.cells[midRow * 8 + midCol];
        const landCell = board.cells[landRow * 8 + landCol];

        if (!midCell || midCell.player !== opponent || landCell !== null) {
            continue;
        }

        if (alreadyCaptured.some((c) => c.row === midRow && c.col === midCol)) {
            continue;
        }

        const newCaptures = [...alreadyCaptured, { row: midRow, col: midCol }];
        const newPath = [...pathSoFar, { row: landRow, col: landCol }];
        const becomesKing = (player === 1 && landRow === 0) || (player === 2 && landRow === 7);

        // King promotion ends the chain.
        if (becomesKing) {
            sequences.push({ from_row: row, from_col: col, to_row: landRow, to_col: landCol, captures: newCaptures, path: newPath });
            continue;
        }

        const tempCells = [...board.cells];
        tempCells[midRow * 8 + midCol] = null;
        tempCells[row * 8 + col] = null;
        tempCells[landRow * 8 + landCol] = { player, isKing: false };

        const further = getManCaptureSequences({ cells: tempCells }, landRow, landCol, player, newCaptures, newPath);

        if (further.length === 0) {
            sequences.push({ from_row: row, from_col: col, to_row: landRow, to_col: landCol, captures: newCaptures, path: newPath });
        } else {
            for (const seq of further) {
                sequences.push({ ...seq, from_row: row, from_col: col, path: seq.path });
            }
        }
    }

    return sequences;
}

function getKingCaptureSequences(
    board: BoardState,
    row: number,
    col: number,
    player: 1 | 2,
    alreadyCaptured: Cell[],
    pathSoFar: Cell[],
): ValidMove[] {
    const opponent = player === 1 ? 2 : 1;
    const dirs: [number, number][] = [[-1, -1], [-1, 1], [1, -1], [1, 1]];
    const sequences: ValidMove[] = [];

    for (const [dr, dc] of dirs) {
        let sr = row + dr;
        let sc = col + dc;
        let foundOpponent: Cell | null = null;

        while (sr >= 0 && sr < 8 && sc >= 0 && sc < 8) {
            const scell = board.cells[sr * 8 + sc];

            if (scell !== null) {
                if (scell.player === opponent && !alreadyCaptured.some((c) => c.row === sr && c.col === sc)) {
                    foundOpponent = { row: sr, col: sc };
                }

                break;
            }

            sr += dr;
            sc += dc;
        }

        if (!foundOpponent) {
            continue;
        }

        let lr = foundOpponent.row + dr;
        let lc = foundOpponent.col + dc;

        while (lr >= 0 && lr < 8 && lc >= 0 && lc < 8 && board.cells[lr * 8 + lc] === null) {
            const newCaptures = [...alreadyCaptured, foundOpponent];
            const newPath = [...pathSoFar, { row: lr, col: lc }];

            const tempCells = [...board.cells];
            tempCells[foundOpponent.row * 8 + foundOpponent.col] = null;
            tempCells[row * 8 + col] = null;
            tempCells[lr * 8 + lc] = { player, isKing: true };

            const further = getKingCaptureSequences({ cells: tempCells }, lr, lc, player, newCaptures, newPath);

            if (further.length === 0) {
                sequences.push({ from_row: row, from_col: col, to_row: lr, to_col: lc, captures: newCaptures, path: newPath });
            } else {
                for (const seq of further) {
                    sequences.push({ ...seq, from_row: row, from_col: col, path: seq.path });
                }
            }

            lr += dr;
            lc += dc;
        }
    }

    return sequences;
}

function getPlayerToken(): string {
    return window.location.pathname.replace('/game/', '');
}

function getCsrfToken(): string {
    const cookie = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    if (!cookie) {
        return '';
    }

    return decodeURIComponent(cookie.split('=')[1]);
}
