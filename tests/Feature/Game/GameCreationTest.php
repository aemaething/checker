<?php

namespace Tests\Feature\Game;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_renders(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('GameCreate'));
    }

    public function test_store_creates_game_and_redirects_to_player1_link(): void
    {
        $response = $this->post('/games');

        $response->assertRedirect();

        $game = Game::first();
        $this->assertNotNull($game);

        $response->assertRedirectContains("/game/{$game->player1_token}");
    }

    public function test_store_creates_game_with_waiting_status(): void
    {
        $this->post('/games');

        $this->assertDatabaseHas('games', ['status' => GameStatus::Waiting->value]);
    }

    public function test_player1_sees_waiting_status_and_share_url(): void
    {
        $game = Game::factory()->create();

        $response = $this->get("/game/{$game->player1_token}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Game')
                ->where('game.status', 'waiting')
                ->where('game.player_number', 1)
                ->has('game.share_url')
            );
    }

    public function test_player2_joining_activates_game(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Waiting]);

        $this->get("/game/{$game->player2_token}");

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'status' => GameStatus::Active->value,
        ]);
    }

    public function test_player2_sees_active_status_after_joining(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Waiting]);

        $response = $this->get("/game/{$game->player2_token}");

        $response->assertInertia(fn ($page) => $page
            ->where('game.status', 'active')
            ->where('game.player_number', 2)
            ->where('game.share_url', null)
        );
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->get('/game/invalid-token-xyz')->assertStatus(404);
    }

    public function test_player2_does_not_receive_share_url(): void
    {
        $game = Game::factory()->active()->create();

        $response = $this->get("/game/{$game->player2_token}");

        $response->assertInertia(fn ($page) => $page->where('game.share_url', null));
    }
}
