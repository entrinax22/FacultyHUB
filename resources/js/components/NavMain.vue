<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
const { isMobile, setOpenMobile } = useSidebar();

function handleNavigation() {
    if (isMobile.value) {
        setOpenMobile(false);
    }
}
</script>

<template>
    <SidebarGroup class="px-3 py-0">
        <SidebarGroupLabel class="px-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-sidebar-foreground/55">
            {{ label ?? 'Navigation' }}
        </SidebarGroupLabel>
        <SidebarMenu class="gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    size="default"
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href" class="min-h-10" @click="handleNavigation">
                        <component :is="item.icon" />
                        <span class="font-medium">{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
