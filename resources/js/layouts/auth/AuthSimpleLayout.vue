<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BrandLogo from '@/components/BrandLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-6">
                <!-- Brand header -->
                <div class="flex flex-col items-center gap-3">
                    <Link :href="home()" class="flex items-center gap-2.5">
                        <BrandLogo class-name="h-auto w-44" />
                    </Link>
                    <div class="space-y-1 text-center">
                        <h1 class="text-lg font-semibold">{{ title }}</h1>
                        <p class="text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
