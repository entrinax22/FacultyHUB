<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/FacultyHUB-LMS%20Logo/facultyhub-icon.svg"
        alt="FacultyHUB"
        :class="className"
        v-bind="$attrs"
    />
</template>
