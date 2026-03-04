<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Checkerboard: dark squares only, 4×4 grid, each cell 10×10 -->
        <rect x="0"  y="0"  width="10" height="10" fill="currentColor"/>
        <rect x="20" y="0"  width="10" height="10" fill="currentColor"/>
        <rect x="10" y="10" width="10" height="10" fill="currentColor"/>
        <rect x="30" y="10" width="10" height="10" fill="currentColor"/>
        <rect x="0"  y="20" width="10" height="10" fill="currentColor"/>
        <rect x="20" y="20" width="10" height="10" fill="currentColor"/>
        <rect x="10" y="30" width="10" height="10" fill="currentColor"/>
        <rect x="30" y="30" width="10" height="10" fill="currentColor"/>
    </svg>
</template>
