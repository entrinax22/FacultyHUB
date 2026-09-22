```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle,
    Download,
    FileText,
} from 'lucide-vue-next';
import { ref } from 'vue';
import axios from 'axios';
import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

type ModuleFile = {
    id: string;
    file_name: string;
    file_type: string;
    size_formatted: string;
    url: string;
};

type Module = {
    id: string;
    title: string;
    description: string | null;
    week_number: number | null;
    files: ModuleFile[];
    section: {
        id: string;
        name: string;
        subject: {
            code: string;
            name: string;
        };
        semester: {
            name: string;
            school_year: string;
        };
    };
};

const props = defineProps<{
    module: Module;
    isRead: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Classes',
                href: '/my-sections',
            },
            {
                title: 'Module',
                href: '#',
            },
        ],
    },
});

const marked = ref(props.isRead);

async function markAsRead() {
    try {
        const response = await axios.post(
            `/modules/${props.module.id}/mark-read`,
        );

        showApiToast(response);

        if (response.data.success) {
            marked.value = true;
        }
    } catch (error: any) {
        console.error('MARK MODULE READ ERROR:', error);

        showApiError(error);
    }
}
</script>

<template>
    <Head :title="module.title" />

    <div
        class="flex min-h-full w-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <div class="w-full max-w-3xl space-y-5 sm:space-y-6">

            <!-- HEADER -->

            <div
                class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <!-- Module information -->

                <div class="flex min-w-0 items-start gap-2 sm:gap-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        as-child
                        class="-ml-2 mt-0.5 shrink-0"
                    >
                        <Link
                            :href="`/my-sections/${module.section.id}`"
                        >
                            <ArrowLeft class="h-4 w-4" />

                            <span class="sr-only">
                                Back to section
                            </span>
                        </Link>
                    </Button>

                    <div class="min-w-0 flex-1">

                        <!-- Title + badges -->

                        <div
                            class="flex min-w-0 flex-wrap items-center gap-2"
                        >
                            <h1
                                class="min-w-0 break-words text-xl font-semibold sm:text-2xl"
                            >
                                {{ module.title }}
                            </h1>

                            <span
                                v-if="module.week_number"
                                class="shrink-0 rounded-md bg-muted px-2 py-0.5 text-xs"
                            >
                                Week {{ module.week_number }}
                            </span>

                            <Badge
                                v-if="marked"
                                variant="outline"
                                class="shrink-0 border-green-200 text-green-600"
                            >
                                <CheckCircle
                                    class="mr-1 h-3 w-3 shrink-0"
                                />

                                Read
                            </Badge>
                        </div>

                        <!-- Section -->

                        <p
                            class="mt-1 break-words text-xs text-muted-foreground sm:text-sm"
                        >
                            <span class="font-medium text-foreground">
                                {{ module.section.subject.code }}
                            </span>
                            ·
                            {{ module.section.name }}
                        </p>
                    </div>
                </div>

                <!-- Mark as read -->

                <Button
                    v-if="!marked"
                    size="sm"
                    variant="outline"
                    class="w-full shrink-0 sm:w-auto"
                    @click="markAsRead"
                >
                    <CheckCircle
                        class="mr-2 h-4 w-4 shrink-0"
                    />

                    Mark as Read
                </Button>
            </div>

            <!-- DESCRIPTION -->

            <div
                v-if="module.description"
                class="rounded-xl border bg-muted/30 px-4 py-3 sm:px-5 sm:py-4"
            >
                <p
                    class="break-words text-sm leading-relaxed text-muted-foreground"
                >
                    {{ module.description }}
                </p>
            </div>

            <!-- FILES -->

            <div
                v-if="module.files.length"
                class="min-w-0 space-y-2"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                >
                    Files
                </p>

                <a
                    v-for="file in module.files"
                    :key="file.id"
                    :href="file.url"
                    class="flex min-w-0 items-center gap-3 rounded-xl border bg-card px-3 py-3 transition-colors hover:bg-muted/40 sm:px-4"
                >
                    <FileText
                        class="h-5 w-5 shrink-0 text-muted-foreground"
                    />

                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-sm font-medium"
                            :title="file.file_name"
                        >
                            {{ file.file_name }}
                        </p>

                        <p
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            {{ file.size_formatted }}
                        </p>
                    </div>

                    <Download
                        class="h-4 w-4 shrink-0 text-muted-foreground"
                    />
                </a>
            </div>

            <!-- NO FILES -->

            <div
                v-else
                class="flex min-h-40 flex-col items-center justify-center rounded-xl border border-dashed p-6 text-center sm:min-h-48"
            >
                <FileText
                    class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30"
                />

                <p class="text-sm text-muted-foreground">
                    No files attached to this module.
                </p>
            </div>
        </div>
    </div>
</template>
```
