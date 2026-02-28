<script setup lang="ts">
import CheckersBoard from '@/components/CheckersBoard.vue';
import GameStatus from '@/components/GameStatus.vue';
import { useCheckers } from '@/composables/useCheckers';
import type { Game, MoveMadeEvent } from '@/types/game';
import { usePoll } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { ref, watch } from 'vue';

const props = defineProps<{
    game: Game;
}>();

// Local reactive copy so optimistic updates work without Inertia clobbering.
const localGame = ref<Game>({ ...props.game, board_state: { cells: [...props.game.board_state.cells] } });

// Keep local copy in sync when Inertia reloads the page.
watch(
    () => props.game,
    (newGame) => {
        localGame.value = { ...newGame, board_state: { cells: [...newGame.board_state.cells] } };
    },
    { deep: true },
);

const { selectedCell, highlightedCells, isMyTurn, moveError, selectPiece, applyBroadcastedMove } = useCheckers(localGame);

// WebSocket: listen for opponent moves on a public channel.
useEchoPublic<MoveMadeEvent>(`game.${localGame.value.uuid}`, 'MoveMade', (event) => {
    applyBroadcastedMove(event);
});

// Polling as async fallback (stops when game is finished).
const { stop: stopPolling } = usePoll(
    5000,
    { only: ['game'] },
    { autoStart: localGame.value.status !== 'finished' },
);

watch(
    () => localGame.value.status,
    (status) => {
        if (status === 'finished') {
            stopPolling();
        }
    },
);
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-6 bg-amber-50 p-4">
        <h1 class="text-2xl font-bold text-amber-900">Dame</h1>

        <div class="flex w-full max-w-2xl flex-col items-center gap-6">
            <!-- Game status / share link -->
            <div class="w-full max-w-lg rounded-xl border border-amber-200 bg-white p-4 shadow">
                <GameStatus :game="localGame" />
            </div>

            <!-- Error banner -->
            <div
                v-if="moveError"
                class="w-full max-w-lg rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700"
            >
                {{ moveError }}
            </div>

            <!-- Waiting skeleton -->
            <div
                v-if="localGame.status === 'waiting' && localGame.player_number === 1"
                class="w-full max-w-lg aspect-square animate-pulse rounded-lg bg-amber-200"
            />

            <!-- Board -->
            <CheckersBoard
                v-else
                :board-state="localGame.board_state"
                :selected-cell="selectedCell"
                :highlighted-cells="highlightedCells"
                :is-my-turn="isMyTurn"
                :player-number="localGame.player_number"
                @cell-click="selectPiece"
            />
        </div>
    </div>
</template>
