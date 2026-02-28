<script setup lang="ts">
import type { CellPiece } from '@/types/game';

defineProps<{
    piece: CellPiece;
    isSelected: boolean;
}>();
</script>

<template>
    <div
        class="flex h-full w-full cursor-pointer items-center justify-center"
        :class="[
            'rounded-full border-2 transition-all duration-150',
            piece.player === 1
                ? 'border-gray-900 bg-black shadow-md'
                : 'border-slate-700 bg-slate-300 shadow-md',
            isSelected ? 'scale-110 ring-4 ring-yellow-400' : 'hover:scale-105',
        ]"
    >
        <!-- King crown indicator -->
        <span v-if="piece.isKing" class="select-none text-xs font-bold" :class="piece.player === 1 ? 'text-white' : 'text-slate-700'">
            ♛
        </span>
    </div>
</template>
