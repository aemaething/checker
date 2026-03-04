<script setup lang="ts">
import { computed } from 'vue';
import CheckersPiece from '@/components/CheckersPiece.vue';
import type { BoardState } from '@/types/game';

const props = defineProps<{
    boardState: BoardState;
    selectedCell: { row: number; col: number } | null;
    highlightedCells: { row: number; col: number }[];
    isMyTurn: boolean;
    playerNumber: 1 | 2;
}>();

const emit = defineEmits<{
    cellClick: [row: number, col: number];
}>();

// Player 2 sees the board flipped (their pieces at the bottom).
const rows = computed(() => {
    const r = [0, 1, 2, 3, 4, 5, 6, 7];
    return props.playerNumber === 2 ? [...r].reverse() : r;
});

const cols = computed(() => {
    const c = [0, 1, 2, 3, 4, 5, 6, 7];
    return props.playerNumber === 2 ? [...c].reverse() : c;
});

function isDarkSquare(row: number, col: number): boolean {
    return (row + col) % 2 === 1;
}

function isSelected(row: number, col: number): boolean {
    return props.selectedCell?.row === row && props.selectedCell?.col === col;
}

function isHighlighted(row: number, col: number): boolean {
    return props.highlightedCells.some((c) => c.row === row && c.col === col);
}

function getPiece(row: number, col: number) {
    return props.boardState.cells[row * 8 + col] ?? null;
}
</script>

<template>
    <div class="aspect-square w-full max-w-lg rounded-xl bg-zinc-800 p-3
                border-t-[6px] border-t-zinc-100
                border-b-[6px] border-b-zinc-950
                border-x-[6px] border-x-zinc-800
                shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)]">
        <div class="grid h-full w-full grid-cols-8 grid-rows-8 overflow-hidden
                    rounded-sm border-2 border-black/80 bg-zinc-700
                    shadow-[inset_0_10px_20px_rgba(0,0,0,0.6)]">
            <div
                v-for="row in rows"
                :key="`row-${row}`"
                class="contents"
            >
                <div
                    v-for="col in cols"
                    :key="`${row}-${col}`"
                    class="relative flex items-center justify-center p-[10%]"
                    :class="[
                        isDarkSquare(row, col) ? 'bg-zinc-600' : 'bg-zinc-400',
                        isDarkSquare(row, col) && isMyTurn ? 'cursor-pointer' : '',
                        isHighlighted(row, col) ? 'ring-4 ring-inset ring-yellow-400' : '',
                        isSelected(row, col) ? 'brightness-125' : '',
                    ]"
                    @click="emit('cellClick', row, col)"
                >
                    <CheckersPiece
                        v-if="getPiece(row, col)"
                        :piece="getPiece(row, col)!"
                        :is-selected="isSelected(row, col)"
                    />

                    <!-- Valid move dot indicator -->
                    <div
                        v-else-if="isHighlighted(row, col)"
                        class="h-3 w-3 rounded-full bg-yellow-400/80"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
