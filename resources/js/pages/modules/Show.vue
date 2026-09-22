<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Download,
    FileText,
    Pencil,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ModuleFile = {
    id: string;
    file_name: string;
    file_type: string;
    file_size: number;
    file_url: string;
};

type Module = {
    id: string;
    title: string;
    description: string | null;
    week_number: number | null;
    is_published: boolean;
    files: ModuleFile[];

    section: {
        id: string;
        name: string;

        subject: {
            id?: string;
            code: string;
            name: string;
        };

        semester: {
            id?: string;
            name: string;
            school_year: string;
        };
    };
};

defineProps<{
    module: Module;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Modules', href: '#' },
        ],
    },
});

function formatFileSize(bytes: number): string {
    if (bytes === 0) {
        return '0 Bytes';
    }

    const units = [
        'Bytes',
        'KB',
        'MB',
        'GB',
        'TB',
    ];

    const index = Math.floor(
        Math.log(bytes) / Math.log(1024),
    );

    return `${parseFloat(
        (bytes / Math.pow(1024, index)).toFixed(2),
    )} ${units[index]}`;
}
</script>

<template>
    <Head :title="module.title" />

    <div
        class="flex h-full max-w-2xl flex-1 flex-col gap-6 p-4"
    >
        <!-- Header -->
        <div
            class="flex items-start justify-between gap-3"
        >
            <div class="flex items-start gap-3">
                <!-- Back -->
                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="-ml-2 mt-0.5"
                >
                    <Link
                        :href="
                            `/sections/${module.section.id}/modules`
                        "
                    >
                        <ArrowLeft
                            class="h-4 w-4"
                        />
                    </Link>
                </Button>

                <div>
                    <div
                        class="flex flex-wrap items-center gap-2"
                    >
                        <h1
                            class="text-xl font-semibold"
                        >
                            {{ module.title }}
                        </h1>

                        <span
                            v-if="module.week_number"
                            class="rounded-md bg-muted px-2 py-0.5 text-xs"
                        >
                            Week {{ module.week_number }}
                        </span>

                        <Badge
                            :variant="
                                module.is_published
                                    ? 'default'
                                    : 'secondary'
                            "
                        >
                            {{
                                module.is_published
                                    ? 'Published'
                                    : 'Draft'
                            }}
                        </Badge>
                    </div>

                    <p
                        class="text-sm text-muted-foreground"
                    >
                        {{ module.section.subject.code }}
                        ·
                        {{ module.section.name }}
                    </p>
                </div>
            </div>

            <!-- Edit -->
            <Button
                variant="outline"
                size="sm"
                as-child
                class="shrink-0"
            >
                <Link
                    :href="
                        `/modules/${module.id}/edit`
                    "
                >
                    <Pencil
                        class="mr-1.5 h-3.5 w-3.5"
                    />
                    Edit
                </Link>
            </Button>
        </div>

        <!-- Description -->
        <p
            v-if="module.description"
            class="rounded-xl border bg-muted/30 px-4 py-3 text-sm leading-relaxed text-muted-foreground"
        >
            {{ module.description }}
        </p>

        <!-- Files -->
        <div
            v-if="module.files.length"
            class="space-y-2"
        >
            <p
                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
            >
                Files
            </p>

            <a
                v-for="file in module.files"
                :key="file.id"
                :href="file.file_url"
                class="flex items-center gap-3 rounded-xl border bg-card px-4 py-3 transition-colors hover:bg-muted/40"
            >
                <FileText
                    class="h-5 w-5 shrink-0 text-muted-foreground"
                />

                <div class="min-w-0 flex-1">
                    <p
                        class="truncate text-sm font-medium"
                    >
                        {{ file.file_name }}
                    </p>

                    <p
                        class="text-xs text-muted-foreground"
                    >
                        {{ formatFileSize(file.file_size) }}
                    </p>
                </div>

                <Download
                    class="h-4 w-4 shrink-0 text-muted-foreground"
                />
            </a>
        </div>

        <!-- No files -->
        <div
            v-else
            class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            <FileText
                class="mx-auto mb-2 h-8 w-8 opacity-30"
            />

            <p class="text-sm">
                No files attached to this module.
            </p>

            <Button
                variant="outline"
                size="sm"
                class="mt-3"
                as-child
            >
                <Link
                    :href="
                        `/modules/${module.id}/edit`
                    "
                >
                    Upload Files
                </Link>
            </Button>
        </div>
    </div>
</template>