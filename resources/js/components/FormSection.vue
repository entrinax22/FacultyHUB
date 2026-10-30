<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        class?: HTMLAttributes['class'];
        variant?: 'card' | 'plain' | 'divided';
    }>(),
    {
        description: undefined,
        class: undefined,
        variant: 'card',
    },
);
</script>

<template>
    <section
        :class="
            cn(
                props.variant === 'card'
                    ? 'rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-6'
                    : props.variant === 'divided'
                      ? 'border-b border-border/70 pb-6 last:border-b-0 last:pb-0'
                      : 'rounded-none border-0 bg-transparent p-0 shadow-none',
                $props.class,
            )
        "
    >
        <div class="mb-5">
            <h2 class="text-base font-semibold tracking-tight sm:text-lg">
                {{ title }}
            </h2>
            <p
                v-if="description"
                class="mt-1 text-sm leading-6 text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>

        <div class="space-y-5">
            <slot />
        </div>
    </section>
</template>
