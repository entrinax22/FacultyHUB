<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-6">
                <!-- Brand header -->
                <div class="flex flex-col items-center gap-3">
                    <Link :href="home()" class="flex items-center gap-2.5">
                        <div class="flex aspect-square size-10 items-center justify-center rounded-xl brand-gradient shadow-md">
                            <svg viewBox="0 0 24 24" fill="none" class="size-6">
                                <circle cx="12" cy="12" r="2.5" fill="hsl(142 30% 12%)"/>
                                <circle cx="5"  cy="7"  r="1.8" fill="hsl(142 30% 12%)"/>
                                <circle cx="19" cy="7"  r="1.8" fill="hsl(142 30% 12%)"/>
                                <circle cx="5"  cy="17" r="1.8" fill="hsl(142 30% 12%)"/>
                                <circle cx="19" cy="17" r="1.8" fill="hsl(142 30% 12%)"/>
                                <line x1="12" y1="12" x2="5"  y2="7"  stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                                <line x1="12" y1="12" x2="19" y2="7"  stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                                <line x1="12" y1="12" x2="5"  y2="17" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                                <line x1="12" y1="12" x2="19" y2="17" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                            </svg>
                        </div>
                        <span class="text-xl font-bold tracking-tight">Faculty<span class="brand-gradient-text">HUB</span></span>
                    </Link>
                    <div class="space-y-1 text-center">
                        <h1 class="text-lg font-semibold">{{ title }}</h1>
                        <p class="text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
