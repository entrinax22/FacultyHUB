<script setup lang="ts">
import { Toaster as SonnerPrimitive } from 'vue-sonner';
import 'vue-sonner/style.css';
import { useAppearance } from '@/composables/useAppearance';

const { appearance } = useAppearance();
</script>

<template>
    <SonnerPrimitive
        :theme="appearance"
        class="toaster group"
        position="top-right"
        :rich-colors="true"
        :close-button="true"
        :style="{
            '--normal-bg': 'var(--popover)',
            '--normal-text': 'var(--popover-foreground)',
            '--normal-border': 'var(--border)',
        }"
    />
</template>

<style>
[data-sonner-toast][data-type='success'][data-styled='true'] {
    background: #15803d !important;
    border-color: #166534 !important;
    color: #ffffff !important;
    box-shadow: 0 10px 25px rgb(21 128 61 / 25%), 0 2px 6px rgb(0 0 0 / 10%) !important;
}

[data-sonner-toast][data-type='success'][data-styled='true'] [data-description],
[data-sonner-toast][data-type='success'][data-styled='true'] [data-title] {
    color: #ffffff !important;
}

[data-sonner-toast][data-type='error'][data-styled='true'] {
    background: #b91c1c !important;
    border-color: #991b1b !important;
    color: #ffffff !important;
}

[data-sonner-toast][data-type='error'][data-styled='true'] [data-description],
[data-sonner-toast][data-type='error'][data-styled='true'] [data-title] {
    color: #ffffff !important;
}

[data-sonner-toast][data-type='success'][data-styled='true'] [data-close-button],
[data-sonner-toast][data-type='error'][data-styled='true'] [data-close-button] {
    background: rgb(255 255 255 / 18%) !important;
    border-color: rgb(255 255 255 / 35%) !important;
    color: #ffffff !important;
}
</style>
