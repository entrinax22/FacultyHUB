<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        class?: HTMLAttributes['class'];
    }>(),
    {
        description: undefined,
        icon: undefined,
        class: undefined,
    },
);
</script>

<template>
    <header
        :class="
            cn(
                'flex min-w-0 flex-col gap-4 sm:flex-row sm:items-center sm:justify-between',
                $props.class,
            )
        "
    >
        <div class="flex min-w-0 items-start gap-3">
            <div
                v-if="icon"
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                aria-hidden="true"
            >
                <component :is="icon" class="size-5" />
            </div>

            <div class="min-w-0">
                <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mt-1 text-xs leading-5 text-muted-foreground sm:text-sm"
                >
                    {{ description }}
                </p>
            </div>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
