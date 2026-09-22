<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { X, Upload, FileText, BookOpen } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';

import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

type ModuleFile = {
    id: string;
    file_name: string;
    file_type: string;
    file_size: number;
    file_url: string;
};

type Section = {
    id: string;
    name: string;
    schedule?: string | null;
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

type Module = {
    id: string;
    title: string;
    description: string | null;
    week_number: number | null;
    is_published: boolean;
    files: ModuleFile[];
    section_id?: string;
};

const props = defineProps<{
    section: Section;
    module?: Module;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Modules', href: '#' },
        ],
    },
});

const form = ref({
    title: props.module?.title ?? '',
    description: props.module?.description ?? '',
    week_number: props.module?.week_number?.toString() ?? '',
    is_published: props.module?.is_published ?? false,
    files: [] as File[],
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFileNames = ref<string[]>([]);

function clearError(field: string) {
    delete errors.value[field];
}

function onFilesChange(event: Event) {
    const input = event.target as HTMLInputElement;

    if (!input.files) {
        return;
    }

    const newFiles = Array.from(input.files);

    form.value.files = [
        ...form.value.files,
        ...newFiles,
    ];

    selectedFileNames.value = form.value.files.map(
        (file) => file.name,
    );

    clearError('files');

    // Allow selecting the same file again
    input.value = '';
}

function removeNewFile(index: number) {
    form.value.files = form.value.files.filter(
        (_, i) => i !== index,
    );

    selectedFileNames.value = form.value.files.map(
        (file) => file.name,
    );
}

async function removeExistingFile(fileId: string) {
    if (!confirm('Remove this file?')) {
        return;
    }

    try {
        const response = await axios.delete(
            `/module-files/${fileId}`,
        );

        showApiToast(response);

        if (props.module) {
            props.module.files = props.module.files.filter(
                (file) => file.id !== fileId,
            );
        }
    } catch (error) {
        console.error('Failed to remove module file:', error);
        showApiError(error);
    }
}

async function submit() {
    if (processing.value) {
        return;
    }

    processing.value = true;
    errors.value = {};

    try {
        const formData = new FormData();

        formData.append('title', form.value.title);
        formData.append(
            'description',
            form.value.description,
        );

        if (form.value.week_number) {
            formData.append(
                'week_number',
                form.value.week_number,
            );
        }

        formData.append(
            'is_published',
            form.value.is_published ? '1' : '0',
        );

        form.value.files.forEach((file) => {
            formData.append('files[]', file);
        });

        let response;

        if (props.module) {
            response = await axios.post(
                `/modules/${props.module.id}`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                    params: {
                        _method: 'PUT',
                    },
                },
            );
        } else {
            response = await axios.post(
                `/sections/${props.section.id}/modules`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                },
            );
        }

        showApiToast(response);

        window.location.href =
            `/sections/${props.section.id}/modules`;
    } catch (error: any) {
        console.error('Failed to save module:', error);

        if (error.response?.status === 422) {
            errors.value =
                error.response.data.errors ?? {};
        } else {
            showApiError(error);
        }
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Head :title="module ? 'Edit Module' : 'New Module'" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10"
            >
                <BookOpen class="h-5 w-5 text-primary" />
            </div>

            <div>
                <h1 class="text-xl font-semibold">
                    {{ module ? 'Edit Module' : 'New Module' }}
                </h1>

                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} · {{ section.name }}
                </p>
            </div>
        </div>

        <!-- Form -->
        <form
            @submit.prevent="submit"
            class="max-w-2xl space-y-5 rounded-xl border bg-card p-6 shadow-sm"
            enctype="multipart/form-data"
        >
            <!-- Title -->
            <div class="grid gap-1.5">
                <Label for="title">
                    Module Title
                </Label>

                <Input
                    id="title"
                    v-model="form.title"
                    placeholder="e.g. Introduction to HTML"
                    required
                    :disabled="processing"
                    @input="clearError('title')"
                />

                <InputError :message="errors.title" />
            </div>

            <!-- Description -->
            <div class="grid gap-1.5">
                <Label for="description">
                    Description
                    <span class="text-muted-foreground">
                        (optional)
                    </span>
                </Label>

                <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    placeholder="Brief overview of what this module covers"
                    :disabled="processing"
                    @input="clearError('description')"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                ></textarea>

                <InputError :message="errors.description" />
            </div>

            <!-- Week -->
            <div class="grid max-w-xs gap-1.5">
                <Label for="week_number">
                    Week Number
                    <span class="text-muted-foreground">
                        (optional)
                    </span>
                </Label>

                <Input
                    id="week_number"
                    type="number"
                    v-model="form.week_number"
                    min="1"
                    max="52"
                    placeholder="e.g. 1"
                    :disabled="processing"
                    @input="clearError('week_number')"
                />

                <InputError :message="errors.week_number" />
            </div>

            <!-- Existing files -->
            <div
                v-if="module?.files?.length"
                class="grid gap-2"
            >
                <Label>
                    Existing Files
                </Label>

                <div class="space-y-1.5">
                    <div
                        v-for="file in module.files"
                        :key="file.id"
                        class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                    >
                        <div
                            class="flex min-w-0 items-center gap-2"
                        >
                            <FileText
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />

                            <span class="truncate">
                                {{ file.file_name }}
                            </span>
                        </div>

                        <button
                            type="button"
                            class="ml-3 shrink-0 text-destructive hover:text-destructive/80 disabled:opacity-50"
                            :disabled="processing"
                            @click="removeExistingFile(file.id)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Upload Files -->
            <div class="grid gap-2">
                <Label>
                    Upload Files
                    <span class="text-muted-foreground">
                        (PDF, DOCX, PPT, images, ZIP — max 50 MB each)
                    </span>
                </Label>

                <div
                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 transition-colors hover:bg-muted/30"
                    :class="{
                        'pointer-events-none opacity-50': processing,
                    }"
                    @click="fileInput?.click()"
                >
                    <Upload
                        class="mb-2 h-6 w-6 text-muted-foreground"
                    />

                    <p class="text-sm text-muted-foreground">
                        Click to choose files or drag and drop
                    </p>

                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.jpg,.jpeg,.png,.gif,.webp"
                        class="hidden"
                        @change="onFilesChange"
                    />
                </div>

                <InputError :message="errors.files" />

                <!-- Selected new files -->
                <div
                    v-if="selectedFileNames.length"
                    class="space-y-1.5"
                >
                    <div
                        v-for="(name, i) in selectedFileNames"
                        :key="i"
                        class="flex items-center justify-between rounded-lg border bg-muted/20 px-3 py-2 text-sm"
                    >
                        <div
                            class="flex min-w-0 items-center gap-2"
                        >
                            <FileText
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />

                            <span class="truncate">
                                {{ name }}
                            </span>
                        </div>

                        <button
                            type="button"
                            class="ml-3 shrink-0 text-muted-foreground hover:text-foreground"
                            :disabled="processing"
                            @click="removeNewFile(i)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Publish -->
            <div class="flex items-center gap-3">
                <Checkbox
                    id="is_published"
                    v-model:checked="form.is_published"
                    :disabled="processing"
                />

                <Label
                    for="is_published"
                    class="cursor-pointer"
                >
                    Publish immediately (visible to enrolled students)
                </Label>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 border-t pt-5">
                <Button
                    type="submit"
                    :disabled="processing"
                >
                    {{
                        processing
                            ? 'Saving...'
                            : module
                                ? 'Update Module'
                                : 'Create Module'
                    }}
                </Button>

                <Button
                    variant="outline"
                    as-child
                    :disabled="processing"
                >
                    <Link
                        :href="`/sections/${section.id}/modules`"
                    >
                        Cancel
                    </Link>
                </Button>
            </div>
        </form>
    </div>
</template>