<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { store as storeGame } from '@/actions/App/Http/Controllers/GameController';

const form = useForm({});

function createGame(): void {
    form.post(storeGame().url);
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-zinc-300">
        <div class="w-full max-w-md rounded-2xl bg-zinc-100 p-10 shadow-xl text-center space-y-8">
            <!-- Board decoration -->
            <div class="mx-auto grid w-24 grid-cols-4 grid-rows-4 gap-0.5 overflow-hidden rounded">
                <div
                    v-for="i in 16"
                    :key="i"
                    class="aspect-square"
                    :class="(Math.floor((i - 1) / 4) + (i - 1)) % 2 === 0 ? 'bg-zinc-600' : 'bg-zinc-400'"
                />
            </div>

            <div>
                <h1 class="text-3xl font-bold text-zinc-900">Dame</h1>
                <p class="mt-2 text-zinc-500">Deutsches Damespiel · 8×8</p>
            </div>

            <button
                :disabled="form.processing"
                class="w-full rounded-xl bg-zinc-800 px-6 py-3 text-lg font-semibold text-white shadow transition hover:bg-zinc-700 disabled:opacity-50"
                @click="createGame"
            >
                {{ form.processing ? 'Spiel wird erstellt…' : 'Neues Spiel starten' }}
            </button>

            <p class="text-xs text-zinc-400">
                Nach dem Erstellen bekommst du einen Link, den du mit deinem Mitspieler teilen kannst.
            </p>
        </div>
    </div>
</template>
