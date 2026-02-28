export type CellPiece = {
    player: 1 | 2;
    isKing: boolean;
};

export type BoardState = {
    cells: (CellPiece | null)[];
};

export type GameStatus = 'waiting' | 'active' | 'finished';

export type Game = {
    uuid: string;
    status: GameStatus;
    current_turn: 1 | 2;
    winner: 1 | 2 | null;
    board_state: BoardState;
    player_number: 1 | 2;
    share_url: string | null;
};

export type MovePayload = {
    from_row: number;
    from_col: number;
    to_row: number;
    to_col: number;
};

export type ValidMove = MovePayload & {
    captures: { row: number; col: number }[];
    path: { row: number; col: number }[]; // intermediate landing squares + final destination
};

export type MoveMadeEvent = {
    board_state: BoardState;
    current_turn: 1 | 2;
    status: GameStatus;
    winner: 1 | 2 | null;
    move: MovePayload & {
        player_number: 1 | 2;
        captures: { row: number; col: number }[] | null;
    };
};
