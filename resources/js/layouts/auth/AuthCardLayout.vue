<script setup lang="ts">
import BrandLogo from '@/components/BrandLogo.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="min-h-svh bg-muted px-4 py-6 sm:px-6 sm:py-8 lg:px-10 lg:py-12 xl:px-12 2xl:px-16"
    >
        <div
            class="mx-auto flex w-full max-w-5xl flex-col gap-6 xl:max-w-6xl 2xl:max-w-7xl"
        >
            <!-- Brand -->
            <div class="flex items-center justify-center py-1">
            <BrandLogo
                class-name="h-auto w-44 sm:w-52 lg:w-60"
            />
            </div>

            <!-- Single Auth Card -->
            <div
                class="w-full overflow-hidden rounded-2xl border bg-background shadow-sm lg:rounded-3xl"
            >
                <div
                    v-if="title || description"
                    class="px-5 pt-7 pb-6 text-center sm:px-8 sm:pt-8 lg:px-12 lg:pt-10 lg:pb-8 xl:px-16 xl:pt-12"
                >
                    <h1
                        v-if="title"
                        class="text-xl font-semibold tracking-tight sm:text-2xl lg:text-3xl"
                    >
                        {{ title }}
                    </h1>

                    <p
                        v-if="description"
                        class="mx-auto mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground lg:text-base"
                    >
                        {{ description }}
                    </p>
                </div>

                <div
                    :class="[
                        'px-5 py-7 sm:px-8 sm:py-8 lg:px-12 lg:py-10 xl:px-16 xl:py-12',
                        (title || description) && 'border-t',
                    ]"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
