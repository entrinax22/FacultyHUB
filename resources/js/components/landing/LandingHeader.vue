<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BarChart2, ChevronDown, LogOut, User } from 'lucide-vue-next';
import BrandLogo from '@/components/BrandLogo.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { login, register } from '@/routes';
import type { User as AuthUser } from '@/types';

const props = defineProps<{
    user: AuthUser | null;
    canRegister: boolean;
    dashboardUrl: string;
    dashboardLabel: string;
}>();

const emit = defineEmits<{
    logout: [];
}>();
</script>

<template>
    <header
        class="sticky top-0 z-50 border-b border-border/60 bg-background/85 backdrop-blur-xl"
    >
        <div
            class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8"
        >
            <Link
                href="/"
                aria-label="FacultyHUB home"
                class="group h-10 w-40 shrink-0"
            >
                <BrandLogo class-name="h-full w-full" />
            </Link>

            <nav
                aria-label="Primary navigation"
                class="flex items-center gap-1 sm:gap-2"
            >
                <div class="mr-2 hidden items-center gap-5 md:flex">
                    <a
                        href="#features"
                        class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >Features</a
                    >
                    <a
                        href="#audiences"
                        class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >For faculty & students</a
                    >
                </div>

                <template v-if="props.user">
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="hidden sm:inline-flex"
                    >
                        <Link :href="props.dashboardUrl">{{
                            props.dashboardLabel
                        }}</Link>
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                class="ml-1 rounded-full px-2.5 sm:px-3"
                                aria-label="Open account menu"
                            >
                                <span
                                    class="brand-gradient flex size-7 items-center justify-center rounded-full"
                                >
                                    <User class="brand-cta-text size-4" />
                                </span>
                                <span
                                    class="hidden max-w-32 truncate text-sm font-medium sm:inline"
                                    >{{ props.user.name }}</span
                                >
                                <ChevronDown
                                    class="size-4 text-muted-foreground"
                                />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-60">
                            <div class="px-3 py-2.5">
                                <p class="truncate text-sm font-semibold">
                                    {{ props.user.name }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ props.user.email }}
                                </p>
                                <span
                                    class="mt-2 inline-flex rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground capitalize"
                                    >{{ props.user.role }}</span
                                >
                            </div>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link
                                    :href="props.dashboardUrl"
                                    class="flex cursor-pointer items-center gap-2"
                                >
                                    <BarChart2 class="size-4" />
                                    {{ props.dashboardLabel }}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="cursor-pointer text-destructive focus:text-destructive"
                                @click="emit('logout')"
                            >
                                <LogOut class="size-4" /> Log out
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>

                <template v-else>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button
                        v-if="props.canRegister"
                        size="sm"
                        class="brand-gradient brand-cta-text border-0 font-semibold"
                        as-child
                    >
                        <Link :href="register()">Get started</Link>
                    </Button>
                </template>
            </nav>
        </div>
    </header>
</template>
