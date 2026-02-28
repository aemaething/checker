<script setup lang="ts">
import CheckersPiece from '@/components/CheckersPiece.vue';
import type { BoardState } from '@/types/game';
import { computed } from 'vue';

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
    <div class="aspect-square w-full max-w-lg overflow-hidden rounded-lg border-4 border-amber-900 shadow-2xl">
        <div class="grid h-full w-full grid-cols-8 grid-rows-8">
            <div
                v-for="row in rows"
                :key="`row-${row}`"
                class="contents"
            >
                <div
                    v-for="col in cols"
                    :key="`${row}-${col}`"
                    class="relative flex items-center justify-center p-1"
                    :class="[
                        isDarkSquare(row, col) ? 'bg-amber-800' : 'bg-amber-100',
                        isDarkSquare(row, col) && isMyTurn ? 'cursor-pointer' : '',
                        isHighlighted(row, col) ? 'ring-4 ring-inset ring-green-400' : '',
                        isSelected(row, col) ? 'bg-amber-700' : '',
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
                        class="h-4 w-4 rounded-full bg-green-400 opacity-70"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
