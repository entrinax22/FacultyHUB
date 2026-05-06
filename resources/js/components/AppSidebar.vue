<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, BookMarked, GraduationCap, CalendarDays, Layers, Users, BarChart3 } from 'lucide-vue-next';
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
import type { NavItem } from '@/types';
import { computed } from 'vue';

const page = usePage();
const role = computed(() => (page.props.auth as any).role as string | undefined);

const commonNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
];

const facultyNavItems: NavItem[] = [
    { title: 'Semesters',   href: '/semesters', icon: CalendarDays },
    { title: 'Subjects',    href: '/subjects',   icon: BookMarked },
    { title: 'Sections',    href: '/sections',   icon: Layers },
    { title: 'Students',    href: '/students',   icon: GraduationCap },
];

const adminNavItems: NavItem[] = [
    { title: 'Dashboard',   href: '/admin',         icon: LayoutGrid },
    { title: 'Users',       href: '/admin/users',   icon: Users },
    { title: 'Reports',     href: '/admin/reports', icon: BarChart3 },
    { title: 'Semesters',   href: '/semesters',     icon: CalendarDays },
    { title: 'Subjects',    href: '/subjects',       icon: BookMarked },
    { title: 'Sections',    href: '/sections',       icon: Layers },
    { title: 'Students',    href: '/students',       icon: GraduationCap },
];

const studentNavItems: NavItem[] = [
    { title: 'My Classes', href: '/my-sections', icon: GraduationCap },
];

const mainNavItems = computed<NavItem[]>(() => {
    if (role.value === 'admin') {
        return adminNavItems;
    }
    if (role.value === 'faculty') {
        return [...commonNavItems, ...facultyNavItems];
    }
    if (role.value === 'student') {
        return [...commonNavItems, ...studentNavItems];
    }
    return commonNavItems;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
