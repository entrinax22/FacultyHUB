<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Eye, EyeOff, Pencil, Trash2, GripVertical, FileText, BookOpen } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ModuleFile = {
    id: number;
    file_name: string;
    file_type: string;
    size_formatted: string;
};

type Module = {
    id: number;
    title: string;
    description: string | null;
    week_number: number | null;
    order: number;
    is_published: boolean;
    files: ModuleFile[];
};

type Section = {
    id: number;
    name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};

const props = defineProps<{ section: Section; modules: Module[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Modules', href: '#' },
        ],
    },
});

const dragging = ref<number | null>(null);
const localModules = ref<Module[]>([...props.modules]);

function onDragStart(index: number) {
    dragging.value = index;
}

function onDragOver(index: number) {
    if (dragging.value === null || dragging.value === index) return;
    const items = [...localModules.value];
    const [moved] = items.splice(dragging.value, 1);
    items.splice(index, 0, moved);
    dragging.value = index;
    localModules.value = items;
}

function onDragEnd() {
    dragging.value = null;
    router.post(
        `/sections/${props.section.id}/modules/reorder`,
        { order: localModules.value.map((m) => m.id) },
        { preserveScroll: true },
    );
}

function togglePublish(moduleId: number) {
    router.post(`/modules/${moduleId}/toggle-publish`, {}, { preserveScroll: true });
}

function deleteModule(moduleId: number, title: string) {
    if (confirm(`Delete "${title}"? All files will be removed.`)) {
        router.delete(`/modules/${moduleId}`);
    }
}
</script>

<template>
    <Head :title="`Modules — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Modules</h1>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} · {{ section.name }} · {{ section.semester.name }} {{ section.semester.school_year }}
                </p>
            </div>
            <Button as-child>
                <Link :href="`/sections/${section.id}/modules/create`">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Module
                </Link>
            </Button>
        </div>

        <div v-if="localModules.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No modules yet. Add your first learning module.
        </div>

        <!-- Drag-and-drop module list -->
        <div v-else class="space-y-2">
            <p class="text-xs text-muted-foreground">Drag rows to reorder modules</p>

            <div
                v-for="(mod, index) in localModules"
                :key="mod.id"
                draggable="true"
                @dragstart="onDragStart(index)"
                @dragover.prevent="onDragOver(index)"
                @dragend="onDragEnd"
                class="flex items-center gap-3 rounded-xl border bg-card p-4 transition-opacity"
                :class="{ 'opacity-50': dragging === index }"
            >
                <!-- Drag handle -->
                <GripVertical class="h-5 w-5 shrink-0 cursor-grab text-muted-foreground" />

                <!-- Week badge -->
                <div class="w-14 shrink-0 text-center">
                    <span v-if="mod.week_number" class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium">
                        Wk {{ mod.week_number }}
                    </span>
                </div>

                <!-- Title + description -->
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ mod.title }}</p>
                    <p v-if="mod.description" class="line-clamp-1 text-xs text-muted-foreground">{{ mod.description }}</p>
                    <div v-if="mod.files.length" class="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                        <FileText class="h-3 w-3" />
                        {{ mod.files.length }} file{{ mod.files.length !== 1 ? 's' : '' }}
                    </div>
                </div>

                <!-- Status badge -->
                <Badge :variant="mod.is_published ? 'default' : 'secondary'" class="shrink-0">
                    {{ mod.is_published ? 'Published' : 'Draft' }}
                </Badge>

                <!-- Actions -->
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        :title="mod.is_published ? 'Set to Draft' : 'Publish'"
                        @click="togglePublish(mod.id)"
                    >
                        <Eye v-if="!mod.is_published" class="h-4 w-4" />
                        <EyeOff v-else class="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="`/modules/${mod.id}`">
                            <BookOpen class="h-4 w-4" />
                        </Link>
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="`/modules/${mod.id}/edit`">
                            <Pencil class="h-4 w-4" />
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="deleteModule(mod.id, mod.title)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
