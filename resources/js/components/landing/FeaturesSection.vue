<script setup lang="ts">
import type { Component } from 'vue';
import { ref } from 'vue';
import { useLandingReveal } from '@/composables/useLandingReveal';
import FeatureCard from './FeatureCard.vue';

export interface LandingFeature {
    icon: Component;
    title: string;
    description: string;
    tone?: string;
}

defineProps<{
    features: LandingFeature[];
}>();

const section = ref<HTMLElement | null>(null);
useLandingReveal(section);
</script>

<template>
    <section
        ref="section"
        id="features"
        class="scroll-mt-20 bg-muted/30 py-16 sm:py-24"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                class="landing-reveal mx-auto mb-10 max-w-2xl text-center sm:mb-12"
                data-landing-reveal
            >
                <p
                    class="text-xs font-bold tracking-[0.2em] text-primary uppercase"
                >
                    Everything in one place
                </p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                    A focused toolkit for the whole class
                </h2>
                <p
                    class="mt-4 text-sm leading-6 text-muted-foreground sm:text-base"
                >
                    Replace scattered tools with one shared academic workspace
                    that keeps teaching and learning moving.
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
                <div
                    v-for="(feature, index) in features"
                    :key="feature.title"
                    class="landing-reveal"
                    data-landing-reveal
                    :style="{ transitionDelay: index * 80 + 'ms' }"
                >
                    <FeatureCard v-bind="feature" />
                </div>
            </div>
        </div>
    </section>
</template>
