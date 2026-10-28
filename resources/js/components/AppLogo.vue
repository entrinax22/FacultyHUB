<template>
    <img
        :src="variant === 'light'
            ? '/FacultyHUB-LMS%20Logo/facultyhub-horizontal-light.svg'
            : '/FacultyHUB-LMS%20Logo/facultyhub-horizontal-dark.svg'"
        alt="FacultyHUB LMS"
        class="h-9 w-36 shrink-0 object-contain object-left group-data-[collapsible=icon]:hidden"
    />
    <img
        src="/FacultyHUB-LMS%20Logo/facultyhub-icon.svg"
        alt="FacultyHUB"
        class="hidden size-8 shrink-0 object-contain group-data-[collapsible=icon]:block"
    />
</template>

<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'dark' | 'light';
    }>(),
    {
        variant: 'dark',
    },
);
</script>
