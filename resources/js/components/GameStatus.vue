<script setup lang="ts">
import type { Game } from '@/types/game';
import { computed, ref } from 'vue';

const props = defineProps<{
    game: Game;
}>();

const copied = ref(false);

const statusMessage = computed(() => {
    if (props.game.status === 'waiting') {
        return props.game.player_number === 1
            ? 'Warte auf Mitspieler…'
            : 'Spiel startet…';
    }

    if (props.game.status === 'finished') {
        return props.game.winner === props.game.player_number
            ? 'Gewonnen! 🎉'
            : 'Verloren.';
    }

    return props.game.current_turn === props.game.player_number
        ? 'Du bist dran'
        : 'Gegner ist dran…';
});

const statusColor = computed(() => {
    if (props.game.status === 'finished') {
        return props.game.winner === props.game.player_number
            ? 'text-green-600'
            : 'text-red-600';
    }

    return props.game.current_turn === props.game.player_number
        ? 'text-blue-600'
        : 'text-slate-500';
});

async function copyShareLink(): Promise<void> {
    if (!props.game.share_url) {
        return;
    }

    await navigator.clipboard.writeText(props.game.share_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <div class="space-y-3">
        <!-- Turn / status indicator -->
        <div class="flex items-center gap-2">
            <span
                v-if="game.status === 'active' && game.current_turn === game.player_number"
                class="h-3 w-3 animate-pulse rounded-full bg-blue-500"
            />
            <span class="font-semibold" :class="statusColor">
                {{ statusMessage }}
            </span>
        </div>

        <!-- Player info -->
        <div class="text-sm text-slate-500">
            Du spielst als
            <span class="font-medium" :class="game.player_number === 1 ? 'text-gray-900' : 'text-slate-500'">
                Spieler {{ game.player_number }}
                {{ game.player_number === 1 ? '(Schwarz)' : '(Grau)' }}
            </span>
        </div>

        <!-- Share link for player 1 while waiting -->
        <div v-if="game.share_url && game.status === 'waiting'" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
            <p class="mb-2 text-sm font-medium text-amber-800">Link für Mitspieler:</p>
            <div class="flex gap-2">
                <input
                    :value="game.share_url"
                    readonly
                    class="min-w-0 flex-1 rounded border border-amber-200 bg-white px-2 py-1 text-xs text-slate-600"
                />
                <button
                    class="rounded bg-amber-700 px-3 py-1 text-xs text-white transition hover:bg-amber-800"
                    @click="copyShareLink"
                >
                    {{ copied ? 'Kopiert!' : 'Kopieren' }}
                </button>
            </div>
        </div>

        <!-- Winner banner -->
        <div
            v-if="game.status === 'finished'"
            class="rounded-lg p-4 text-center font-bold text-xl"
            :class="game.winner === game.player_number ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
        >
            {{ game.winner === game.player_number ? 'Du hast gewonnen! 🎉' : `Spieler ${game.winner} hat gewonnen.` }}
        </div>
    </div>
</template>
