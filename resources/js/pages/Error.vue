<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Home, ArrowLeft, ShieldOff, SearchX, ServerCrash, WifiOff } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{ status: number }>();

const config = computed(() => {
    const map: Record<number, { icon: any; title: string; description: string }> = {
        403: {
            icon: ShieldOff,
            title: 'Access Denied',
            description: "You don't have permission to access this page. If you believe this is a mistake, please contact your administrator.",
        },
        404: {
            icon: SearchX,
            title: 'Page Not Found',
            description: "The page you're looking for doesn't exist or has been moved. Check the URL or head back to the dashboard.",
        },
        500: {
            icon: ServerCrash,
            title: 'Server Error',
            description: 'Something went wrong on our end. The team has been notified. Please try again in a moment.',
        },
        503: {
            icon: WifiOff,
            title: 'Service Unavailable',
            description: 'The system is temporarily unavailable for maintenance. Please check back shortly.',
        },
    };
    return map[props.status] ?? {
        icon: ServerCrash,
        title: 'Unexpected Error',
        description: 'An unexpected error occurred. Please try again or contact support.',
    };
});
</script>

<template>
    <Head :title="`${status} — ${config.title}`" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-8 bg-muted/30 p-6">
        <!-- Brand -->
        <Link href="/" class="flex items-center gap-2.5">
            <div class="flex aspect-square size-9 items-center justify-center rounded-xl brand-gradient shadow-md">
                <svg viewBox="0 0 24 24" fill="none" class="size-5">
                    <circle cx="12" cy="12" r="2.5" fill="hsl(142 30% 12%)"/>
                    <circle cx="5" cy="7" r="1.8" fill="hsl(142 30% 12%)"/>
                    <circle cx="19" cy="7" r="1.8" fill="hsl(142 30% 12%)"/>
                    <circle cx="5" cy="17" r="1.8" fill="hsl(142 30% 12%)"/>
                    <circle cx="19" cy="17" r="1.8" fill="hsl(142 30% 12%)"/>
                    <line x1="12" y1="12" x2="5" y2="7" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                    <line x1="12" y1="12" x2="19" y2="7" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                    <line x1="12" y1="12" x2="5" y2="17" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                    <line x1="12" y1="12" x2="19" y2="17" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                </svg>
            </div>
            <span class="text-lg font-bold tracking-tight">Faculty<span class="brand-gradient-text">HUB</span></span>
        </Link>

        <!-- Error card -->
        <div class="w-full max-w-md rounded-2xl border bg-card p-8 shadow-sm text-center space-y-5">
            <!-- Status code -->
            <div class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-muted">
                <component :is="config.icon" class="h-9 w-9 text-muted-foreground" />
                <span class="absolute -top-1 -right-1 flex h-7 w-12 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                    {{ status }}
                </span>
            </div>

            <div class="space-y-2">
                <h1 class="text-2xl font-bold">{{ config.title }}</h1>
                <p class="text-sm text-muted-foreground leading-relaxed">{{ config.description }}</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:justify-center">
                <Button as-child>
                    <Link href="/dashboard">
                        <Home class="mr-2 h-4 w-4" />
                        Go to Dashboard
                    </Link>
                </Button>
                <Button variant="outline" @click="history.back()">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Go Back
                </Button>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            FacultyHUB · Smart LMS for Faculty
        </p>
    </div>
</template>
