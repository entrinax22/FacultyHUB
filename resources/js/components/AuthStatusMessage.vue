<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    message?: string;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        v-if="props.message"
        role="status"
        aria-live="polite"
        :class="
            cn(
                'rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-400',
                props.class,
            )
        "
    >
        {{ props.message }}
    </div>
</template>
