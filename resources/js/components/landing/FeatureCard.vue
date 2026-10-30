<script setup lang="ts">
import type { Component } from 'vue';
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    icon: Component;
    title: string;
    description: string;
    tone?: string;
}>();
</script>

<template>
    <Card
        class="group h-full border-border/70 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:border-primary/30 hover:shadow-lg motion-reduce:transition-none"
    >
        <CardContent class="p-6">
            <div
                :class="[
                    'mb-5 flex size-11 items-center justify-center rounded-xl',
                    tone ?? 'bg-primary/10 text-primary',
                ]"
            >
                <component :is="icon" class="size-5" />
            </div>
            <h3 class="font-semibold">{{ title }}</h3>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                {{ description }}
            </p>
        </CardContent>
    </Card>
</template>
