<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import WorkspaceSwitcher from '@/components/WorkspaceSwitcher.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex min-h-14 shrink-0 items-center gap-2 border-b border-border/70 bg-background/95 px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:md:min-h-12 sm:px-4 lg:px-6"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <WorkspaceSwitcher />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="min-w-0 flex-1 overflow-hidden">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </div>
            </template>
        </div>
        <div class="ml-auto shrink-0">
            <NotificationBell endpoint="/staff/notifications" />
        </div>
    </header>
</template>
