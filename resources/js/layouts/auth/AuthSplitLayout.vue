<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <!-- Left brand panel -->
        <div
            class="relative hidden h-full flex-col bg-cover bg-center p-10 lg:flex"
            style="background-image: url('images/bg-hero-logo.png')"
        >
            <!-- Gradient overlay tint -->
            <div class="absolute inset-0 bg-gradient-to-br from-[#7da78c]/90 to-[#4a7a5e]/90" />

            <Link :href="home()" class="relative z-20 flex items-center gap-2.5">
                <div class="flex aspect-square size-9 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                    <svg viewBox="0 0 24 24" fill="none" class="size-5">
                        <circle cx="12" cy="12" r="2.5" fill="black"/>
                        <circle cx="5" cy="7" r="1.8" fill="black"/>
                        <circle cx="19" cy="7" r="1.8" fill="black"/>
                        <circle cx="5" cy="17" r="1.8" fill="black"/>
                        <circle cx="19" cy="17" r="1.8" fill="black"/>
                        <line x1="12" y1="12" x2="5" y2="7" stroke="black" stroke-width="1.3"/>
                        <line x1="12" y1="12" x2="19" y2="7" stroke="black" stroke-width="1.3"/>
                        <line x1="12" y1="12" x2="5" y2="17" stroke="black" stroke-width="1.3"/>
                        <line x1="12" y1="12" x2="19" y2="17" stroke="black" stroke-width="1.3"/>
                    </svg>
                </div>
                <span class="relative z-20 text-lg font-bold text-black">Faculty <span class="text-sidebar-primary">LMS</span></span>
            </Link>
            <div class="relative z-20 mt-auto">
                <p class="text-xl font-medium text-black/90">Smart LMS for Faculty</p>
                <p class="mt-1 text-xl text-black/60">Modules · Assignments · AI Grading · Attendance</p>
            </div>
        </div>

        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>