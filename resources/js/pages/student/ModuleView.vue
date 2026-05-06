<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, Download, FileText } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ref } from 'vue';

type ModuleFile = {
    id: number;
    file_name: string;
    file_type: string;
    size_formatted: string;
    url: string;
};

type Module = {
    id: number;
    title: string;
    description: string | null;
    week_number: number | null;
    files: ModuleFile[];
    section: {
        id: number;
        name: string;
        subject: { code: string; name: string };
        semester: { name: string; school_year: string };
    };
};

const props = defineProps<{ module: Module; isRead: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Classes', href: '/my-sections' },
            { title: 'Module', href: '#' },
        ],
    },
});

const marked = ref(props.isRead);

function markAsRead() {
    router.post(`/modules/${props.module.id}/mark-read`, {}, {
        preserveScroll: true,
        onSuccess: () => { marked.value = true; },
    });
}
</script>

<template>
    <Head :title="module.title" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-2xl">
        <!-- Header -->
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                    <Link :href="`/my-sections/${module.section.id}`">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl font-semibold">{{ module.title }}</h1>
                        <span v-if="module.week_number" class="rounded-md bg-muted px-2 py-0.5 text-xs">Week {{ module.week_number }}</span>
                        <Badge v-if="marked" variant="outline" class="text-green-600 border-green-200">
                            <CheckCircle class="mr-1 h-3 w-3" />
                            Read
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ module.section.subject.code }} · {{ module.section.name }}
                    </p>
                </div>
            </div>

            <Button v-if="!marked" size="sm" variant="outline" class="shrink-0" @click="markAsRead">
                <CheckCircle class="mr-2 h-4 w-4" />
                Mark as Read
            </Button>
        </div>

        <!-- Description -->
        <p v-if="module.description" class="rounded-xl border bg-muted/30 px-4 py-3 text-sm text-muted-foreground leading-relaxed">
            {{ module.description }}
        </p>

        <!-- Files -->
        <div v-if="module.files.length" class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Files</p>
            <a
                v-for="file in module.files"
                :key="file.id"
                :href="file.url"
                class="flex items-center gap-3 rounded-xl border bg-card px-4 py-3 transition-colors hover:bg-muted/40"
            >
                <FileText class="h-5 w-5 shrink-0 text-muted-foreground" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ file.file_name }}</p>
                    <p class="text-xs text-muted-foreground">{{ file.size_formatted }}</p>
                </div>
                <Download class="h-4 w-4 shrink-0 text-muted-foreground" />
            </a>
        </div>

        <div v-else class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
            <FileText class="mx-auto mb-2 h-8 w-8 opacity-30" />
            <p class="text-sm">No files attached to this module.</p>
        </div>
    </div>
</template>
