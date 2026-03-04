<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LayoutGrid, LogOut } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';

function logout(): void {
    router.post('/admin/logout');
}
</script>

<template>
    <div class="flex min-h-screen w-full flex-col">
        <header class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-16 w-full items-center justify-between px-4 md:max-w-7xl">
                <div class="flex items-center gap-6">
                    <AppLogo />
                    <nav class="flex items-center gap-1">
                        <Button variant="ghost" size="sm" class="gap-2" as-child>
                            <a href="/admin">
                                <LayoutGrid class="size-4" />
                                Dashboard
                            </a>
                        </Button>
                    </nav>
                </div>

                <Button variant="ghost" size="sm" class="gap-2 text-muted-foreground" @click="logout">
                    <LogOut class="size-4" />
                    Logout
                </Button>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4 p-4">
            <slot />
        </main>
    </div>
</template>
