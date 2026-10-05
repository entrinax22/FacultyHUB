<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    ChevronDown,
    ChevronRight,
    Compass,
    GraduationCap,
} from 'lucide-vue-next';
import { computed } from 'vue';

import NotificationBell from '@/components/NotificationBell.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import UserMenuContent from '@/components/UserMenuContent.vue';

import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const currentPath = computed(() => page.url.split('?')[0] ?? '');
const inMyClasses = computed(
    () =>
        currentPath.value.startsWith('/my-sections') &&
        !currentPath.value.startsWith('/my-sections/browse'),
);
const inBrowse = computed(() =>
    currentPath.value.startsWith('/my-sections/browse'),
);
</script>

<template>
    <div class="min-h-screen bg-[#f3f6f2] text-[#182b25]">
        <header class="bg-[#153d34] text-white">
            <div
                class="mx-auto flex h-[72px] max-w-screen-2xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8"
            >
                <Link
                    href="/my-sections"
                    class="flex min-w-0 items-center gap-3"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#d8e7b2] text-[#153d34]"
                    >
                        <GraduationCap class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span
                            class="block truncate text-sm font-semibold tracking-wide sm:text-base"
                            >Faculty LMS</span
                        >
                        <span class="hidden text-xs text-[#c4d5cc] sm:block"
                            >Student learning portal</span
                        >
                    </span>
                </Link>

                <div class="flex shrink-0 items-center gap-1">
                    <NotificationBell
                        endpoint="/student/notifications"
                        variant="student"
                    />

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="flex max-w-[220px] items-center gap-2 rounded-lg px-2 py-1.5 text-left transition-colors outline-none hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-[#d8e7b2]"
                                aria-label="Open account menu"
                            >
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#45685c] text-sm font-semibold">
                                    {{ user.name.slice(0, 1).toUpperCase() }}
                                </span>
                                <span class="hidden min-w-0 sm:block">
                                    <span class="block truncate text-sm font-medium">{{ user.name }}</span>
                                    <span class="block text-xs text-[#c4d5cc]">Student</span>
                                </span>
                                <ChevronDown class="hidden size-4 text-[#c4d5cc] sm:block" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-64">
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>

        <nav
            class="border-b border-[#dce5de] bg-white"
            aria-label="Student navigation"
        >
            <div
                class="mx-auto flex max-w-screen-2xl items-center gap-1 px-4 sm:px-6 lg:px-8"
            >
                <Link
                    href="/my-sections"
                    class="inline-flex min-h-12 items-center gap-2 border-b-2 px-3 text-sm font-medium transition-colors"
                    :class="
                        inMyClasses
                            ? 'border-[#35715a] text-[#245641]'
                            : 'border-transparent text-[#66766d] hover:text-[#182b25]'
                    "
                    :aria-current="inMyClasses ? 'page' : undefined"
                >
                    <BookOpen class="size-4" />
                    My classes
                </Link>
                <Link
                    href="/my-sections/browse"
                    class="inline-flex min-h-12 items-center gap-2 border-b-2 px-3 text-sm font-medium transition-colors"
                    :class="
                        inBrowse
                            ? 'border-[#35715a] text-[#245641]'
                            : 'border-transparent text-[#66766d] hover:text-[#182b25]'
                    "
                    :aria-current="inBrowse ? 'page' : undefined"
                >
                    <Compass class="size-4" />
                    Browse classes
                </Link>
            </div>
        </nav>

        <main
            class="mx-auto w-full max-w-screen-2xl px-4 py-5 sm:px-6 sm:py-7 lg:px-8"
        >
            <nav
                v-if="breadcrumbs.length > 1"
                class="mb-5"
                aria-label="Breadcrumb"
            >
                <ol
                    class="flex flex-wrap items-center gap-1.5 text-xs text-[#748078]"
                >
                    <li
                        v-for="(breadcrumb, index) in breadcrumbs"
                        :key="`${breadcrumb.title}-${index}`"
                        class="inline-flex items-center gap-1.5"
                    >
                        <ChevronRight v-if="index > 0" class="size-3.5" />
                        <span
                            v-if="index === breadcrumbs.length - 1"
                            aria-current="page"
                        >
                            {{ breadcrumb.title }}
                        </span>
                        <Link
                            v-else
                            :href="breadcrumb.href"
                            class="transition-colors hover:text-[#245641]"
                        >
                            {{ breadcrumb.title }}
                        </Link>
                    </li>
                </ol>
            </nav>
            <slot />
        </main>

        <Toaster />
    </div>
</template>
