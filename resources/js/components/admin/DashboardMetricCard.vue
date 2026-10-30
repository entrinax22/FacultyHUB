<script setup lang="ts">
import type { Component } from 'vue';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    title: string;
    value: number | string;
    description: string;
    icon: Component;
}>();
</script>

<template>
    <Card class="min-w-0">
        <CardHeader
            class="flex flex-row items-center justify-between gap-3 p-3 pb-1 sm:p-4 sm:pb-1"
        >
            <CardTitle class="text-xs text-muted-foreground">{{
                title
            }}</CardTitle>
            <component
                :is="icon"
                class="size-4 shrink-0 text-primary"
                aria-hidden="true"
            />
        </CardHeader>
        <CardContent class="p-3 pt-1 sm:p-4 sm:pt-1">
            <p class="text-2xl font-bold tabular-nums">{{ value }}</p>
            <p class="mt-1 text-xs leading-5 text-muted-foreground">
                {{ description }}
            </p>
        </CardContent>
    </Card>
</template>
