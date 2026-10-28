<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import {
    administrationNavItems,
    commonNavItems,
    teachingNavItems,
} from '@/config/navigation';
import type { Auth, NavItem, Workspace } from '@/types';

const page = usePage();
const auth = computed(() => page.props.auth as Auth);
const { currentUrl } = useCurrentUrl();
const workspace = computed<Workspace>(() =>
    currentUrl.value.startsWith('/admin') ? 'administration' : 'teaching',
);

const mainNavItems = computed<NavItem[]>(() => {
    if (auth.value.role === 'admin') {
        return workspace.value === 'administration'
            ? administrationNavItems
            : teachingNavItems;
    }

    if (auth.value.role === 'faculty') {
        return [...commonNavItems, ...teachingNavItems];
    }

    return commonNavItems;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-sidebar-border/70">
        <SidebarHeader class="border-b border-sidebar-border/70 px-3 py-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-11 bg-transparent p-1 shadow-none hover:bg-sidebar-accent/60 hover:shadow-none group-data-[collapsible=icon]:p-0!"
                    >
                        <Link href="/dashboard" aria-label="Go to dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-0 py-3">
            <NavMain
                :items="mainNavItems"
                :label="auth.role === 'admin'
                    ? workspace === 'administration' ? 'Administration' : 'Teaching'
                    : 'Teaching'"
            />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/70 px-3 py-3">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
