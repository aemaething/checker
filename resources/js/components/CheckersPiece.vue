<script setup lang="ts">
import type { CellPiece } from '@/types/game';

defineProps<{
    piece: CellPiece;
    isSelected: boolean;
}>();
</script>

<template>
    <div
        class="flex h-full w-full cursor-pointer items-center justify-center rounded-full transition-all duration-150"
        :class="[
            piece.player === 1
                ? 'bg-zinc-900 border-[0.5px] border-zinc-100/20 shadow-[0_5px_0_0_rgba(0,0,0,0.9),0_6px_10px_rgba(0,0,0,0.6),inset_0_2px_2px_rgba(255,255,255,0.1),inset_0_-2px_4px_rgba(0,0,0,0.4)]'
                : 'bg-zinc-100 border-[0.5px] border-zinc-900/50 shadow-[0_5px_0_0_rgba(0,0,0,0.3),0_6px_10px_rgba(0,0,0,0.2),inset_0_2px_2px_rgba(255,255,255,1),inset_0_-2px_4px_rgba(0,0,0,0.1)]',
            isSelected ? 'scale-110 ring-2 ring-yellow-400 ring-offset-1' : 'hover:scale-105',
        ]"
    >
        <!-- King crown indicator -->
        <svg
            v-if="piece.isKing"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            class="size-5 drop-shadow"
            :fill="piece.player === 1 ? '#f4f4f5' : '#18181b'"
        >
            <path d="M2 19h20v2H2v-2ZM3 7l4 7 5-9 5 9 4-7v10H3V7Z" />
        </svg>
    </div>
</template>
