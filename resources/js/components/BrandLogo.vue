<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

withDefaults(
    defineProps<{
        variant?: 'auto' | 'dark' | 'light';
        iconOnly?: boolean;
        className?: HTMLAttributes['class'];
    }>(),
    {
        variant: 'auto',
        iconOnly: false,
    },
);
</script>

<template>
    <span :class="['inline-flex shrink-0', className]">
        <img
            v-if="iconOnly"
            src="/FacultyHUB-LMS%20Logo/facultyhub-icon.svg"
            alt="FacultyHUB"
            class="size-full object-contain"
        />
        <template v-else-if="variant === 'auto'">
            <img
                src="/FacultyHUB-LMS%20Logo/facultyhub-horizontal-light.svg"
                alt="FacultyHUB LMS"
                class="size-full object-contain dark:hidden"
            />
            <img
                src="/FacultyHUB-LMS%20Logo/facultyhub-horizontal-dark.svg"
                alt="FacultyHUB LMS"
                class="hidden size-full object-contain dark:block"
            />
        </template>
        <img
            v-else-if="variant === 'light'"
            src="/FacultyHUB-LMS%20Logo/facultyhub-horizontal-light.svg"
            alt="FacultyHUB LMS"
            class="size-full object-contain"
        />
        <img
            v-else
            src="/FacultyHUB-LMS%20Logo/facultyhub-horizontal-dark.svg"
            alt="FacultyHUB LMS"
            class="size-full object-contain"
        />
    </span>
</template>
