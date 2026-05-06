<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, FileText, Image, ArrowLeft, Pencil, ExternalLink } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ModuleFile = {
    id: number;
    file_name: string;
    file_path: string;
    file_type: string;
    file_size: number;
    size_formatted: string;
    url: string;
};

type Module = {
    id: number;
    title: string;
    description: string | null;
    week_number: number | null;
    is_published: boolean;
    files: ModuleFile[];
    section: {
        id: number;
        name: string;
        subject: { code: string; name: string };
        semester: { name: string; school_year: string };
    };
};

const props = defineProps<{ module: Module }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Modules', href: '#' },
        ],
    },
});

const activeFile = ref<ModuleFile | null>(props.module.files[0] ?? null);

function isViewable(file: ModuleFile): boolean {
    return file.file_type === 'application/pdf' ||
        file.file_type.startsWith('image/');
}

function isPdf(file: ModuleFile): boolean {
    return file.file_type === 'application/pdf';
}

function isImage(file: ModuleFile): boolean {
    return file.file_type.startsWith('image/');
}

function fileIcon(file: ModuleFile) {
    if (isImage(file)) return Image;
    return FileText;
}
</script>

<template>
    <Head :title="module.title" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-3">
                <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                    <Link :href="`/sections/${module.section.id}/modules`">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold">{{ module.title }}</h1>
                        <span v-if="module.week_number" class="rounded-md bg-muted px-2 py-0.5 text-xs">Week {{ module.week_number }}</span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ module.section.subject.code }} · {{ module.section.name }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Badge :variant="module.is_published ? 'default' : 'secondary'">
                    {{ module.is_published ? 'Published' : 'Draft' }}
                </Badge>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/modules/${module.id}/edit`">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" />
                        Edit
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Description -->
        <p v-if="module.description" class="rounded-xl border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
            {{ module.description }}
        </p>

        <!-- Layout: file list + viewer -->
        <div class="flex min-h-0 flex-1 gap-4" :class="module.files.length ? 'flex-row' : ''">
            <!-- File list sidebar -->
            <div v-if="module.files.length" class="w-64 shrink-0 space-y-1.5 overflow-y-auto">
                <p class="px-1 text-xs font-medium text-muted-foreground uppercase tracking-wide">Files</p>
                <button
                    v-for="file in module.files"
                    :key="file.id"
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-lg border px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted/50"
                    :class="activeFile?.id === file.id ? 'bg-muted border-primary/30' : ''"
                    @click="activeFile = file"
                >
                    <component :is="fileIcon(file)" class="h-4 w-4 shrink-0 text-muted-foreground" />
                    <span class="min-w-0 flex-1 truncate">{{ file.file_name }}</span>
                    <span class="shrink-0 text-xs text-muted-foreground">{{ file.size_formatted }}</span>
                </button>
            </div>

            <!-- Viewer panel -->
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

                <!-- Inline viewer -->
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
                        <p class="text-sm">This file type cannot be previewed in the browser.</p>
                        <Button as-child>
                            <a :href="activeFile.url" :download="activeFile.file_name">
                                <Download class="mr-2 h-4 w-4" />
                                Download {{ activeFile.file_name }}
                            </a>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- No files state -->
            <div v-else-if="!module.files.length" class="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed text-muted-foreground">
                <FileText class="h-12 w-12 opacity-30" />
                <p class="text-sm">No files attached to this module yet.</p>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/modules/${module.id}/edit`">Upload Files</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
