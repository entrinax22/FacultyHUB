<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, CheckCircle, Download, ExternalLink, FileText, Image } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

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

const activeFile = ref<ModuleFile | null>(props.module.files[0] ?? null);
const marked = ref(props.isRead);

function markAsRead() {
    router.post(
        `/modules/${props.module.id}/mark-read`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => { marked.value = true; },
        },
    );
}

function isPdf(file: ModuleFile): boolean {
    return file.file_type === 'application/pdf';
}

function isImage(file: ModuleFile): boolean {
    return file.file_type.startsWith('image/');
}
</script>

<template>
    <Head :title="module.title" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex items-start justify-between">
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

            <Button
                v-if="!marked"
                size="sm"
                variant="outline"
                class="shrink-0"
                @click="markAsRead"
            >
                <CheckCircle class="mr-2 h-4 w-4" />
                Mark as Read
            </Button>
        </div>

        <!-- Description -->
        <p v-if="module.description" class="rounded-xl border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
            {{ module.description }}
        </p>

        <!-- Layout -->
        <div class="flex min-h-0 flex-1 gap-4">
            <!-- File list sidebar -->
            <div v-if="module.files.length > 1" class="w-64 shrink-0 space-y-1.5 overflow-y-auto">
                <p class="px-1 text-xs font-medium text-muted-foreground uppercase tracking-wide">Files</p>
                <button
                    v-for="file in module.files"
                    :key="file.id"
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-lg border px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted/50"
                    :class="activeFile?.id === file.id ? 'bg-muted border-primary/30' : ''"
                    @click="activeFile = file"
                >
                    <FileText class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <span class="min-w-0 flex-1 truncate">{{ file.file_name }}</span>
                    <span class="shrink-0 text-xs text-muted-foreground">{{ file.size_formatted }}</span>
                </button>
            </div>

            <!-- Viewer -->
            <div v-if="activeFile" class="flex min-h-0 flex-1 flex-col gap-2">
                <!-- File toolbar -->
                <div class="flex items-center justify-between rounded-lg border px-3 py-2">
                    <span class="truncate text-sm font-medium">{{ activeFile.file_name }}</span>
                    <div class="flex shrink-0 items-center gap-1 ml-3">
                        <Button variant="ghost" size="sm" as-child>
                            <a :href="activeFile.url" target="_blank" rel="noopener">
                                <ExternalLink class="h-4 w-4" />
                            </a>
                        </Button>
                        <Button variant="ghost" size="sm" as-child>
                            <a :href="activeFile.url" :download="activeFile.file_name">
                                <Download class="h-4 w-4" />
                            </a>
                        </Button>
                    </div>
                </div>

                <div class="flex-1 overflow-hidden rounded-xl border">
                    <iframe
                        v-if="isPdf(activeFile)"
                        :src="activeFile.url"
                        class="h-full min-h-[600px] w-full"
                        title="PDF Viewer"
                    />
                    <img
                        v-else-if="isImage(activeFile)"
                        :src="activeFile.url"
                        :alt="activeFile.file_name"
                        class="max-h-[600px] w-full object-contain p-4"
                    />
                    <div v-else class="flex h-64 flex-col items-center justify-center gap-3 text-muted-foreground">
                        <FileText class="h-12 w-12 opacity-40" />
                        <p class="text-sm">This file cannot be previewed in the browser.</p>
                        <Button as-child>
                            <a :href="activeFile.url" :download="activeFile.file_name">
                                <Download class="mr-2 h-4 w-4" />
                                Download File
                            </a>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- No files -->
            <div v-else class="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed text-muted-foreground">
                <FileText class="h-12 w-12 opacity-30" />
                <p class="text-sm">This module has no attached files.</p>
            </div>
        </div>
    </div>
</template>
