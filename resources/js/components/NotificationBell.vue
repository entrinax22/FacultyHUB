<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Bell,
    BookOpen,
    CheckCheck,
    ClipboardList,
    GraduationCap,
} from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type Props = {
    endpoint: string;
    variant?: 'default' | 'student';
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
});

type ActivityNotification = {
    id: string;
    type: string;
    title: string;
    message: string;
    url: string;
    read_at: string | null;
    created_at: string;
};

const notifications = ref<ActivityNotification[]>([]);
const unreadCount = ref(0);
const loadingNotifications = ref(true);
const notificationsOpen = ref(false);
let refreshTimer: number | undefined;

async function loadNotifications() {
    try {
        const response = await axios.get<{
            data: ActivityNotification[];
            unread_count: number;
        }>(props.endpoint);

        notifications.value = response.data.data;
        unreadCount.value = response.data.unread_count;
    } catch (error) {
        console.error('Failed to load notifications:', error);
    } finally {
        loadingNotifications.value = false;
    }
}

async function markNotificationRead(id: string) {
    const notification = notifications.value.find((item) => item.id === id);

    if (!notification || notification.read_at) {
        return;
    }

    try {
        const response = await axios.post(
            `${props.endpoint}/${id}/read`,
        );

        notification.read_at = new Date().toISOString();
        unreadCount.value = response.data.unread_count;
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
}

async function markAllNotificationsRead() {
    try {
        await axios.post(`${props.endpoint}/read-all`);
        const readAt = new Date().toISOString();

        notifications.value.forEach((notification) => {
            notification.read_at ??= readAt;
        });
        unreadCount.value = 0;
    } catch (error) {
        console.error('Failed to mark notifications as read:', error);
    }
}

async function openNotification(notification: ActivityNotification) {
    await markNotificationRead(notification.id);
    notificationsOpen.value = false;
    router.visit(notification.url);
}

function notificationIcon(type: string) {
    if (type === 'assignment' || type === 'assignment_submission') {
        return ClipboardList;
    }

    if (type === 'module') {
        return BookOpen;
    }

    return GraduationCap;
}

function formatNotificationDate(date: string): string {
    const minutesAgo = Math.floor((Date.now() - new Date(date).getTime()) / 60000);

    if (minutesAgo < 1) {
        return 'Just now';
    }

    if (minutesAgo < 60) {
        return `${minutesAgo}m ago`;
    }

    const hoursAgo = Math.floor(minutesAgo / 60);

    if (hoursAgo < 24) {
        return `${hoursAgo}h ago`;
    }

    return new Date(date).toLocaleDateString();
}

onMounted(() => {
    void loadNotifications();
    refreshTimer = window.setInterval(() => {
        void loadNotifications();
    }, 60_000);
});

onBeforeUnmount(() => {
    if (refreshTimer !== undefined) {
        window.clearInterval(refreshTimer);
    }
});
</script>

<template>
    <DropdownMenu v-model:open="notificationsOpen">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="relative flex size-10 items-center justify-center rounded-lg outline-none transition-colors focus-visible:ring-2"
                :class="variant === 'student'
                    ? 'text-white hover:bg-white/10 focus-visible:ring-[#d8e7b2]'
                    : 'text-foreground hover:bg-accent focus-visible:ring-ring'"
                :aria-label="`Notifications${unreadCount ? `, ${unreadCount} unread` : ''}`"
            >
                <Bell class="size-5" />
                <span
                    v-if="unreadCount"
                    class="absolute top-0.5 right-0.5 flex min-h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-bold"
                    :class="variant === 'student'
                        ? 'bg-[#d8e7b2] text-[#153d34]'
                        : 'bg-destructive text-destructive-foreground'"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="max-h-[min(70vh,32rem)] w-[calc(100vw-2rem)] max-w-sm overflow-y-auto p-0"
        >
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <p class="text-sm font-semibold">Notifications</p>
                    <p class="text-xs text-muted-foreground">
                        {{ unreadCount }} unread
                    </p>
                </div>
                <Button
                    v-if="unreadCount"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-8 px-2 text-xs"
                    @click.stop="markAllNotificationsRead"
                >
                    <CheckCheck class="mr-1.5 size-3.5" />
                    Mark all read
                </Button>
            </div>

            <div
                v-if="loadingNotifications"
                class="px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Loading notifications...
            </div>
            <div v-else-if="notifications.length === 0" class="px-4 py-8 text-center">
                <Bell class="mx-auto mb-2 size-5 text-muted-foreground/60" />
                <p class="text-sm font-medium">You're all caught up</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    New activity will appear here.
                </p>
            </div>
            <div v-else class="divide-y">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-accent/60"
                    :class="notification.read_at
                        ? ''
                        : variant === 'student'
                            ? 'bg-accent/30'
                            : 'bg-muted/40'"
                    @click="openNotification(notification)"
                >
                    <span
                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg"
                        :class="variant === 'student'
                            ? 'bg-primary/10 text-primary'
                            : 'bg-primary/10 text-primary'"
                    >
                        <component
                            :is="notificationIcon(notification.type)"
                            class="size-4"
                        />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-3">
                            <span class="text-sm font-medium">
                                {{ notification.title }}
                            </span>
                            <span class="shrink-0 text-[11px] text-muted-foreground">
                                {{ formatNotificationDate(notification.created_at) }}
                            </span>
                        </span>
                        <span class="mt-0.5 block text-xs leading-relaxed text-muted-foreground">
                            {{ notification.message }}
                        </span>
                    </span>
                    <span
                        v-if="!notification.read_at"
                        class="mt-1.5 size-2 shrink-0 rounded-full"
                        :class="variant === 'student' ? 'bg-primary' : 'bg-primary'"
                    />
                </button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>