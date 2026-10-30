<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useLandingReveal } from '@/composables/useLandingReveal';
import { login } from '@/routes';

const props = defineProps<{
    user: boolean;
    dashboardUrl: string;
    dashboardLabel: string;
}>();

const section = ref<HTMLElement | null>(null);
useLandingReveal(section);
</script>

<template>
    <section
        ref="section"
        id="get-started"
        class="brand-gradient scroll-mt-20 py-16 sm:py-20"
    >
        <div
            class="landing-reveal mx-auto max-w-3xl px-4 text-center sm:px-6"
            data-landing-reveal
        >
            <div
                class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-background/70 shadow-sm"
            >
                <Sparkles class="brand-cta-text size-6" />
            </div>
            <h2
                class="brand-cta-text mt-5 text-3xl font-bold tracking-tight sm:text-4xl"
            >
                Make more room for meaningful learning
            </h2>
            <p class="brand-cta-muted mx-auto mt-3 max-w-xl text-sm leading-6">
                A shared, organized home for the everyday work of teaching,
                learning, and academic progress.
            </p>
            <Button
                size="lg"
                class="brand-cta-surface mt-7 font-semibold shadow-md"
                as-child
            >
                <Link :href="props.user ? props.dashboardUrl : login()"
                    >{{
                        props.user
                            ? props.dashboardLabel
                            : 'Log in to FacultyHUB'
                    }}
                    <ArrowRight class="size-4"
                /></Link>
            </Button>
        </div>
    </section>
</template>
