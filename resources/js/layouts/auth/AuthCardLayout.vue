<script setup lang="ts">
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
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex aspect-square size-9 items-center justify-center rounded-xl brand-gradient shadow-md sm:size-10 lg:size-11"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            class="size-5 sm:size-5.5 lg:size-6"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="2.5"
                                fill="hsl(142 30% 12%)"
                            />
                            <circle
                                cx="5"
                                cy="7"
                                r="1.8"
                                fill="hsl(142 30% 12%)"
                            />
                            <circle
                                cx="19"
                                cy="7"
                                r="1.8"
                                fill="hsl(142 30% 12%)"
                            />
                            <circle
                                cx="5"
                                cy="17"
                                r="1.8"
                                fill="hsl(142 30% 12%)"
                            />
                            <circle
                                cx="19"
                                cy="17"
                                r="1.8"
                                fill="hsl(142 30% 12%)"
                            />
                            <line
                                x1="12"
                                y1="12"
                                x2="5"
                                y2="7"
                                stroke="hsl(142 30% 12%)"
                                stroke-width="1.3"
                            />
                            <line
                                x1="12"
                                y1="12"
                                x2="19"
                                y2="7"
                                stroke="hsl(142 30% 12%)"
                                stroke-width="1.3"
                            />
                            <line
                                x1="12"
                                y1="12"
                                x2="5"
                                y2="17"
                                stroke="hsl(142 30% 12%)"
                                stroke-width="1.3"
                            />
                            <line
                                x1="12"
                                y1="12"
                                x2="19"
                                y2="17"
                                stroke="hsl(142 30% 12%)"
                                stroke-width="1.3"
                            />
                        </svg>
                    </div>

                    <span
                        class="text-lg font-bold tracking-tight sm:text-xl lg:text-2xl"
                    >
                        Faculty<span class="brand-gradient-text">HUB</span>
                    </span>
                </div>
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