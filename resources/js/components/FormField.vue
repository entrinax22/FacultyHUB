<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        hint?: string;
        error?: string;
        required?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        hint: undefined,
        error: undefined,
        required: false,
        class: undefined,
    },
);

const describedBy = computed(() => {
    const ids = [];

    if (props.hint) {
        ids.push(`${props.id}-hint`);
    }

    if (props.error) {
        ids.push(`${props.id}-error`);
    }

    return ids.length ? ids.join(' ') : undefined;
});
</script>

<template>
    <div :class="cn('grid gap-1.5', $props.class)">
        <label
            :id="`${id}-label`"
            :for="id"
            class="text-sm leading-none font-medium"
        >
            {{ label }}
            <span
                v-if="required"
                class="ml-1 text-destructive"
                aria-hidden="true"
                >*</span
            >
            <span v-if="required" class="sr-only">(required)</span>
        </label>

        <slot :id="id" :described-by="describedBy" />

        <p
            v-if="hint"
            :id="`${id}-hint`"
            class="text-xs leading-5 text-muted-foreground"
        >
            {{ hint }}
        </p>

        <p
            v-if="error"
            :id="`${id}-error`"
            class="text-sm text-destructive"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
