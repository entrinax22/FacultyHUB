<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, Pencil, Trash2, ArrowLeft, ClipboardList, Link2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';

type Section = {
    id: number;
    name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};
type Component = {
    id: number;
    name: string;
    weight_percentage: number;
    period: string | null;
    is_locked: boolean;
};
type Item = {
    id: number;
    section_id: number;
    component_id: number;
    assignment_id: number | null;
    name: string;
    max_score: number;
    order: number;
    is_enabled: boolean;
};

const props = defineProps<{
    section: Section;
    components: Component[];
    items: Item[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Class Record', href: '#' },
            { title: 'Items', href: '#' },
        ],
    },
});

// Per-component add state
const adding = ref<Record<number, { name: string; max_score: string; error: string }>>({});
const processing = ref<number | null>(null);

function getAdd(componentId: number) {
    if (!adding.value[componentId]) {
        adding.value[componentId] = { name: '', max_score: '20', error: '' };
    }
    return adding.value[componentId];
}

function addItem(component: Component) {
    const form = getAdd(component.id);
    form.error = '';

    if (!form.name.trim()) { form.error = 'Name is required.'; return; }
    if (!form.max_score || Number(form.max_score) < 1) { form.error = 'Max score must be at least 1.'; return; }

    processing.value = component.id;
    router.post(`/sections/${props.section.id}/items`, {
        component_id: component.id,
        name: form.name.trim(),
        max_score: Number(form.max_score),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            form.name = '';
            form.max_score = '20';
        },
        onFinish: () => { processing.value = null; },
    });
}

function applyPreset(componentId: number, name: string, max: number) {
    const form = getAdd(componentId);
    form.name = name;
    form.max_score = String(max);
}

// Edit state
const editingId = ref<number | null>(null);
const editName = ref('');
const editMax = ref('');

function startEdit(item: Item) {
    editingId.value = item.id;
    editName.value = item.name;
    editMax.value = String(item.max_score);
}

function cancelEdit() {
    editingId.value = null;
}

function saveEdit(item: Item) {
    router.put(`/items/${item.id}`, {
        name: editName.value,
        max_score: Number(editMax.value),
    }, {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
}

function toggle(item: Item) {
    router.post(`/items/${item.id}/toggle`, {}, { preserveScroll: true });
}

function remove(item: Item) {
    if (confirm(`Remove "${item.name}"? Recorded scores will also be deleted.`)) {
        router.delete(`/items/${item.id}`, { preserveScroll: true });
    }
}

const itemsByComponent = computed(() => {
    const map: Record<number, Item[]> = {};
    for (const c of props.components) map[c.id] = [];
    for (const item of props.items) {
        if (!map[item.component_id]) map[item.component_id] = [];
        map[item.component_id].push(item);
    }
    for (const key of Object.keys(map)) {
        map[Number(key)].sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
    }
    return map;
});

const periodColor: Record<string, string> = {
    midterm: 'border-blue-300 bg-blue-50/50 dark:bg-blue-950/20',
    finals:  'border-purple-300 bg-purple-50/50 dark:bg-purple-950/20',
};
const periodBadge: Record<string, string> = {
    midterm: 'text-blue-700 border-blue-300',
    finals:  'text-purple-700 border-purple-300',
};

// Suggested presets per component (based on name keywords)
function presetsFor(name: string): { label: string; max: number }[] {
    const n = name.toLowerCase();
    if (n.includes('quiz'))       return [{ label: 'Quiz 1', max: 20 }, { label: 'Quiz 2', max: 20 }, { label: 'Quiz 3', max: 20 }];
    if (n.includes('activ'))      return [{ label: 'Activity 1', max: 50 }, { label: 'Activity 2', max: 50 }];
    if (n.includes('assign'))     return [{ label: 'Assignment 1', max: 100 }, { label: 'Assignment 2', max: 100 }];
    if (n.includes('exam') || n.includes('midterm') || n.includes('final'))
                                  return [{ label: 'Written Exam', max: 100 }, { label: 'Practical Exam', max: 50 }];
    if (n.includes('attend'))     return [{ label: 'Attendance', max: 30 }];
    if (n.includes('project'))    return [{ label: 'Project', max: 100 }];
    if (n.includes('recit'))      return [{ label: 'Recitation 1', max: 20 }, { label: 'Recitation 2', max: 20 }];
    return [];
}
</script>

<template>
    <Head :title="`Items — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-start gap-3">
            <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                <Link :href="`/sections/${section.id}/class-record`">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>
            <div class="flex-1">
                <h1 class="text-xl font-semibold">Assessment Items</h1>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} · {{ section.name }} — Add quizzes, activities, exams, etc. under each component.
                </p>
            </div>
            <Button variant="outline" size="sm" as-child>
                <Link :href="`/sections/${section.id}/class-record`">
                    <ClipboardList class="mr-1.5 h-3.5 w-3.5" />
                    Class Record
                </Link>
            </Button>
        </div>

        <!-- No components yet -->
        <div v-if="components.length === 0" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
            <p class="text-sm">No grading components set up yet.</p>
            <Button variant="outline" size="sm" class="mt-3" as-child>
                <Link :href="`/sections/${section.id}/components`">Set Up Components</Link>
            </Button>
        </div>

        <!-- One card per component -->
        <div v-else class="space-y-4 max-w-2xl">
            <div
                v-for="comp in components"
                :key="comp.id"
                class="rounded-xl border overflow-hidden"
                :class="comp.period ? periodColor[comp.period] : ''"
            >
                <!-- Component header -->
                <div class="flex items-center justify-between border-b px-4 py-3 bg-muted/20">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ comp.name }}</span>
                            <Badge variant="outline" class="text-xs" :class="comp.period ? periodBadge[comp.period] : ''">
                                {{ comp.period ? comp.period.charAt(0).toUpperCase() + comp.period.slice(1) : 'General' }}
                                · {{ comp.weight_percentage }}%
                            </Badge>
                            <Badge v-if="comp.is_locked" variant="secondary" class="text-xs">Locked</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ itemsByComponent[comp.id]?.length ?? 0 }} item{{ (itemsByComponent[comp.id]?.length ?? 0) !== 1 ? 's' : '' }}
                            <span v-if="itemsByComponent[comp.id]?.some(i => !i.is_enabled)" class="ml-1 text-orange-500">
                                · {{ itemsByComponent[comp.id]?.filter(i => !i.is_enabled).length }} disabled
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Existing items -->
                <div v-if="itemsByComponent[comp.id]?.length" class="divide-y">
                    <div
                        v-for="item in itemsByComponent[comp.id]"
                        :key="item.id"
                        class="flex items-center gap-3 px-4 py-2.5"
                        :class="!item.is_enabled ? 'opacity-50' : ''"
                    >
                        <!-- Edit mode -->
                        <template v-if="editingId === item.id">
                            <Input v-model="editName" class="h-8 flex-1 text-sm" placeholder="Item name" />
                            <Input v-model="editMax" type="number" min="1" class="h-8 w-20 text-sm text-center" />
                            <Button size="sm" class="h-8 text-xs px-3" @click="saveEdit(item)">Save</Button>
                            <Button size="sm" variant="ghost" class="h-8 text-xs px-2" @click="cancelEdit">✕</Button>
                        </template>

                        <!-- View mode -->
                        <template v-else>
                            <div class="flex-1 min-w-0 flex items-center gap-2">
                                <span class="text-sm font-medium">{{ item.name }}</span>
                                <span class="text-xs text-muted-foreground">/ {{ item.max_score }}</span>
                                <Badge v-if="item.assignment_id" variant="outline" class="text-xs text-sky-600 border-sky-300 gap-1 shrink-0" title="Auto-created from assignment">
                                    <Link2 class="h-3 w-3" /> Assignment
                                </Badge>
                            </div>
                            <Badge
                                variant="outline"
                                class="text-xs shrink-0 cursor-pointer select-none"
                                :class="item.is_enabled ? 'text-green-600 border-green-300 hover:bg-green-50' : 'text-muted-foreground hover:bg-muted/50'"
                                @click="toggle(item)"
                                :title="item.is_enabled ? 'Click to disable' : 'Click to enable'"
                            >
                                {{ item.is_enabled ? 'On' : 'Off' }}
                            </Badge>
                            <Button v-if="!item.assignment_id" variant="ghost" size="sm" class="h-7 w-7 p-0 shrink-0" @click="startEdit(item)">
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button v-if="!item.assignment_id" variant="ghost" size="sm" class="h-7 w-7 p-0 shrink-0 text-destructive hover:text-destructive" @click="remove(item)">
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </template>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="px-4 py-3 text-sm text-muted-foreground">
                    No items yet — add the first one below.
                </div>

                <!-- Add item form (inline, scoped to this component) -->
                <div v-if="!comp.is_locked" class="border-t bg-muted/10 px-4 py-3 space-y-2">
                    <div class="flex items-center gap-2">
                        <Input
                            v-model="getAdd(comp.id).name"
                            class="h-8 flex-1 text-sm"
                            :placeholder="`New item for ${comp.name} (e.g. Quiz 1)`"
                            @keydown.enter="addItem(comp)"
                        />
                        <Input
                            v-model="getAdd(comp.id).max_score"
                            type="number"
                            min="1"
                            class="h-8 w-20 text-center text-sm"
                            placeholder="Max"
                            @keydown.enter="addItem(comp)"
                        />
                        <Button
                            size="sm"
                            class="h-8 shrink-0"
                            :disabled="processing === comp.id"
                            @click="addItem(comp)"
                        >
                            <Plus class="h-3.5 w-3.5 mr-1" />
                            Add
                        </Button>
                    </div>

                    <!-- Inline error -->
                    <p v-if="getAdd(comp.id).error" class="text-xs text-destructive">
                        {{ getAdd(comp.id).error }}
                    </p>

                    <!-- Smart presets -->
                    <div v-if="presetsFor(comp.name).length" class="flex flex-wrap gap-1.5">
                        <span class="text-xs text-muted-foreground self-center">Quick add:</span>
                        <button
                            v-for="p in presetsFor(comp.name)"
                            :key="p.label"
                            type="button"
                            class="rounded-md border px-2 py-0.5 text-xs hover:bg-muted/60 transition-colors"
                            @click="applyPreset(comp.id, p.label, p.max)"
                        >
                            {{ p.label }} <span class="text-muted-foreground">({{ p.max }})</span>
                        </button>
                    </div>
                </div>

                <div v-else class="border-t bg-muted/10 px-4 py-2.5 text-xs text-muted-foreground">
                    This component is locked — unlock it in Components to add items.
                </div>
            </div>
        </div>
    </div>
</template>
