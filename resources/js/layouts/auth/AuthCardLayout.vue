<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
        <div class="flex w-full max-w-md flex-col gap-6">
            <!-- Brand -->
            <Link :href="home()" class="flex items-center justify-center gap-2.5">
                <div class="flex aspect-square size-9 items-center justify-center rounded-xl brand-gradient shadow-md">
                    <svg viewBox="0 0 24 24" fill="none" class="size-5">
                        <circle cx="12" cy="12" r="2.5" fill="hsl(142 30% 12%)"/>
                        <circle cx="5" cy="7" r="1.8" fill="hsl(142 30% 12%)"/>
                        <circle cx="19" cy="7" r="1.8" fill="hsl(142 30% 12%)"/>
                        <circle cx="5" cy="17" r="1.8" fill="hsl(142 30% 12%)"/>
                        <circle cx="19" cy="17" r="1.8" fill="hsl(142 30% 12%)"/>
                        <line x1="12" y1="12" x2="5" y2="7" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                        <line x1="12" y1="12" x2="19" y2="7" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                        <line x1="12" y1="12" x2="5" y2="17" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                        <line x1="12" y1="12" x2="19" y2="17" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                    </svg>
                </div>
                <span class="text-lg font-bold tracking-tight">Faculty<span class="brand-gradient-text">HUB</span></span>
            </Link>

            <Card class="rounded-2xl shadow-sm">
                <CardHeader class="px-8 pt-8 pb-0 text-center">
                    <CardTitle class="text-xl">{{ title }}</CardTitle>
                    <CardDescription>{{ description }}</CardDescription>
                </CardHeader>
                <CardContent class="px-8 py-7">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
