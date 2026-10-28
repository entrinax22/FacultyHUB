<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, GraduationCap, Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { Auth, Workspace } from '@/types';

const page = usePage();
const auth = computed(() => page.props.auth as Auth);
const { currentUrl } = useCurrentUrl();

const activeWorkspace = computed<Workspace>(() =>
    currentUrl.value.startsWith('/admin') ? 'administration' : 'teaching',
);

const workspaceLabel = computed(() =>
    activeWorkspace.value === 'administration' ? 'Administration' : 'My Teaching',
);
</script>

<template>
    <DropdownMenu v-if="auth.role === 'admin'">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="inline-flex min-h-10 max-w-[10.5rem] items-center gap-2 rounded-md border border-border/70 bg-background px-2.5 text-left text-sm font-medium text-foreground shadow-xs outline-none transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring sm:max-w-56 sm:px-3"
                aria-label="Switch workspace"
                title="Switch workspace"
            >
                <Shield v-if="activeWorkspace === 'administration'" class="size-4 shrink-0 text-primary" />
                <GraduationCap v-else class="size-4 shrink-0 text-primary" />
                <span class="truncate">{{ workspaceLabel }}</span>
                <ChevronsUpDown class="ml-auto size-4 shrink-0 text-muted-foreground" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-56">
            <DropdownMenuLabel>Workspace</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link href="/admin/dashboard" class="flex w-full items-center gap-2">
                    <Shield class="size-4" />
                    <span>Administration</span>
                    <Check v-if="activeWorkspace === 'administration'" class="ml-auto size-4" />
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
                <Link href="/sections" class="flex w-full items-center gap-2">
                    <GraduationCap class="size-4" />
                    <span>My Teaching</span>
                    <Check v-if="activeWorkspace === 'teaching'" class="ml-auto size-4" />
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
