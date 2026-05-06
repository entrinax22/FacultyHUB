<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { X, Upload, FileText, BookOpen } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';

type ModuleFile = {
    id: number;
    file_name: string;
    file_type: string;
    size_formatted: string;
    url: string;
};

type Section = {
    id: number;
    name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};

type Module = {
    id: number;
    title: string;
    description: string | null;
    week_number: number | null;
    is_published: boolean;
    files: ModuleFile[];
};

const props = defineProps<{ section: Section; module?: Module }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Modules', href: '#' },
        ],
    },
});

const form = useForm({
    title: props.module?.title ?? '',
    description: props.module?.description ?? '',
    week_number: props.module?.week_number?.toString() ?? '',
    is_published: props.module?.is_published ?? false,
    files: [] as File[],
});

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFileNames = ref<string[]>([]);

function onFilesChange(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files) return;
    const newFiles = Array.from(input.files);
    form.files = [...form.files, ...newFiles];
    selectedFileNames.value = form.files.map((f) => f.name);
}

function removeNewFile(index: number) {
    form.files = form.files.filter((_, i) => i !== index);
    selectedFileNames.value = form.files.map((f) => f.name);
}

function removeExistingFile(fileId: number) {
    if (confirm('Remove this file?')) {
        // Uses its own delete request, then Inertia reloads the page via back()
        window.open(`/module-files/${fileId}`, '_self');
    }
}

function submit() {
    if (props.module) {
        form.put(`/modules/${props.module.id}`, {
            forceFormData: true,
        });
    } else {
        form.post(`/sections/${props.section.id}/modules`, {
            forceFormData: true,
        });
    }
}
</script>

<template>
    <Head :title="module ? 'Edit Module' : 'New Module'" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <BookOpen class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">{{ module ? 'Edit Module' : 'New Module' }}</h1>
                <p class="text-sm text-muted-foreground">{{ section.subject.code }} · {{ section.name }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="max-w-2xl rounded-xl border bg-card p-6 shadow-sm space-y-5" enctype="multipart/form-data">
            <div class="grid gap-1.5">
                <Label for="title">Module Title</Label>
                <Input id="title" v-model="form.title" placeholder="e.g. Introduction to HTML" required />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-1.5">
                <Label for="description">Description <span class="text-muted-foreground">(optional)</span></Label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    placeholder="Brief overview of what this module covers"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-1.5 max-w-xs">
                <Label for="week_number">Week Number <span class="text-muted-foreground">(optional)</span></Label>
                <Input id="week_number" type="number" v-model="form.week_number" min="1" max="52" placeholder="e.g. 1" />
                <InputError :message="form.errors.week_number" />
            </div>

            <!-- Existing files (edit mode) -->
            <div v-if="module?.files?.length" class="grid gap-2">
                <Label>Existing Files</Label>
                <div class="space-y-1.5">
                    <div
                        v-for="file in module.files"
                        :key="file.id"
                        class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <FileText class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span class="truncate">{{ file.file_name }}</span>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ file.size_formatted }}</span>
                        </div>
                        <button
                            type="button"
                            class="ml-3 shrink-0 text-destructive hover:text-destructive/80"
                            @click="removeExistingFile(file.id)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- File upload -->
            <div class="grid gap-2">
                <Label>Upload Files <span class="text-muted-foreground">(PDF, DOCX, PPT, images, ZIP — max 50 MB each)</span></Label>
                <div
                    class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 cursor-pointer hover:bg-muted/30 transition-colors"
                    @click="fileInput?.click()"
                >
                    <Upload class="mb-2 h-6 w-6 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">Click to choose files or drag and drop</p>
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.jpg,.jpeg,.png,.gif,.webp"
                        class="hidden"
                        @change="onFilesChange"
                    />
                </div>
                <InputError :message="form.errors.files" />

                <div v-if="selectedFileNames.length" class="space-y-1.5">
                    <div
                        v-for="(name, i) in selectedFileNames"
                        :key="i"
                        class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm bg-muted/20"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <FileText class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span class="truncate">{{ name }}</span>
                        </div>
                        <button type="button" class="ml-3 shrink-0 text-muted-foreground hover:text-foreground" @click="removeNewFile(i)">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Checkbox id="is_published" v-model:checked="form.is_published" />
                <Label for="is_published" class="cursor-pointer">Publish immediately (visible to enrolled students)</Label>
            </div>

            <div class="flex gap-3 border-t pt-5">
                <Button type="submit" :disabled="form.processing">
                    {{ module ? 'Update Module' : 'Create Module' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/sections/${section.id}/modules`">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
