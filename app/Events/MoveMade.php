<?php

namespace App\Events;

use App\Models\Game;
use App\Models\Move;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MoveMade implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Game $game,
        public readonly Move $move,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new Channel('game.'.$this->game->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MoveMade';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'board_state' => $this->game->board_state,
            'current_turn' => $this->game->current_turn,
            'status' => $this->game->status->value,
            'winner' => $this->game->winner,
            'move' => [
                'from_row' => $this->move->from_row,
                'from_col' => $this->move->from_col,
                'to_row' => $this->move->to_row,
                'to_col' => $this->move->to_col,
                'player_number' => $this->move->player_number,
                'captures' => $this->move->captures,
            ],
        ];
    }
}
