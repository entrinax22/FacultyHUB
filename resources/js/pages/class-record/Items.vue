<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import {
    Plus,
    Pencil,
    Trash2,
    ArrowLeft,
    ClipboardList,
    Link2,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import { showApiToast, showApiError } from '@/lib/flashToast';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type Section = {
    id: string;
    name: string;
    subject: {
        id?: string;
        code: string;
        name: string;
    };
    semester: {
        id?: string | number;
        name: string;
        school_year: string;
    };
};

type Component = {
    id: string;
    name: string;
    weight_percentage: number;
    max_score?: number;
    order?: number;
    period: string | null;
    is_locked: boolean;
};

type Item = {
    id: string;
    component_id: string;
    assignment_id: string | null;
    name: string;
    max_score: number;
    order: number;
    is_enabled: boolean;
};

type ItemsData = {
    section: Section;
    components: Component[];
    items: Item[];
};

type ApiResponse<T> = {
    success: boolean;
    message: string;
    data: T;
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
|
| The Inertia page receives only the encrypted section ID.
| Actual section data is loaded through the JSON /data endpoint.
|
*/

const props = defineProps<{
    sectionId: string;
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

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const section = ref<Section | null>(null);

const components = ref<Component[]>([]);
const items = ref<Item[]>([]);

const loading = ref(true);
const processing = ref<string | null>(null);

const adding = ref<
    Record<
        string,
        {
            name: string;
            max_score: string;
            error: string;
        }
    >
>({});

const editingId = ref<string | null>(null);
const editName = ref('');
const editMax = ref('');

/*
|--------------------------------------------------------------------------
| Load Data
|--------------------------------------------------------------------------
*/

async function loadData() {
    loading.value = true;

    try {
        const response = await axios.get<ApiResponse<ItemsData>>(
            `/sections/${props.sectionId}/items/data`,
        );

        const data = response.data.data;

        section.value = data.section;
        components.value = data.components ?? [];
        items.value = data.items ?? [];
    } catch (error) {
        showApiError(error);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadData();
});

/*
|--------------------------------------------------------------------------
| Add Item State
|--------------------------------------------------------------------------
*/

function getAdd(componentId: string) {
    if (!adding.value[componentId]) {
        adding.value[componentId] = {
            name: '',
            max_score: '20',
            error: '',
        };
    }

    return adding.value[componentId];
}

/*
|--------------------------------------------------------------------------
| Add Item
|--------------------------------------------------------------------------
*/

async function addItem(component: Component) {
    const form = getAdd(component.id);

    form.error = '';

    if (!form.name.trim()) {
        form.error = 'Name is required.';
        return;
    }

    if (!form.max_score || Number(form.max_score) < 1) {
        form.error = 'Max score must be at least 1.';
        return;
    }

    processing.value = component.id;

    try {
        const response = await axios.post(
            `/sections/${props.sectionId}/items`,
            {
                component_id: component.id,
                name: form.name.trim(),
                max_score: Number(form.max_score),
            },
        );

        showApiToast(response);

        form.name = '';
        form.max_score = '20';
        form.error = '';

        await loadData();
    } catch (error) {
        showApiError(error);
    } finally {
        processing.value = null;
    }
}

/*
|--------------------------------------------------------------------------
| Presets
|--------------------------------------------------------------------------
*/

function applyPreset(
    componentId: string,
    name: string,
    max: number,
) {
    const form = getAdd(componentId);

    form.name = name;
    form.max_score = String(max);
    form.error = '';
}

/*
|--------------------------------------------------------------------------
| Edit Item
|--------------------------------------------------------------------------
*/

function startEdit(item: Item) {
    editingId.value = item.id;
    editName.value = item.name;
    editMax.value = String(item.max_score);
}

function cancelEdit() {
    editingId.value = null;
    editName.value = '';
    editMax.value = '';
}

async function saveEdit(item: Item) {
    if (!editName.value.trim()) {
        showApiError({
            response: {
                data: {
                    message: 'Item name is required.',
                },
            },
        });

        return;
    }

    if (!editMax.value || Number(editMax.value) < 1) {
        showApiError({
            response: {
                data: {
                    message: 'Max score must be at least 1.',
                },
            },
        });

        return;
    }

    processing.value = item.id;

    try {
        const response = await axios.put(
            `/items/${item.id}`,
            {
                name: editName.value.trim(),
                max_score: Number(editMax.value),
            },
        );

        showApiToast(response);

        cancelEdit();

        await loadData();
    } catch (error) {
        showApiError(error);
    } finally {
        processing.value = null;
    }
}

/*
|--------------------------------------------------------------------------
| Toggle Item
|--------------------------------------------------------------------------
*/

async function toggle(item: Item) {
    processing.value = item.id;

    try {
        const response = await axios.post(
            `/items/${item.id}/toggle`,
        );

        showApiToast(response);

        await loadData();
    } catch (error) {
        showApiError(error);
    } finally {
        processing.value = null;
    }
}

/*
|--------------------------------------------------------------------------
| Delete Item
|--------------------------------------------------------------------------
*/

async function remove(item: Item) {
    if (
        !confirm(
            `Remove "${item.name}"? Recorded scores will also be deleted.`,
        )
    ) {
        return;
    }

    processing.value = item.id;

    try {
        const response = await axios.delete(
            `/items/${item.id}`,
        );

        showApiToast(response);

        await loadData();
    } catch (error) {
        showApiError(error);
    } finally {
        processing.value = null;
    }
}

/*
|--------------------------------------------------------------------------
| Items Grouped By Component
|--------------------------------------------------------------------------
*/

const itemsByComponent = computed<Record<string, Item[]>>(() => {
    const map: Record<string, Item[]> = {};

    for (const component of components.value) {
        map[component.id] = [];
    }

    for (const item of items.value) {
        if (!map[item.component_id]) {
            map[item.component_id] = [];
        }

        map[item.component_id].push(item);
    }

    return map;
});

/*
|--------------------------------------------------------------------------
| Period Styling
|--------------------------------------------------------------------------
*/

const periodColor: Record<string, string> = {
    midterm:
        'border-blue-300 bg-blue-50/50 dark:bg-blue-950/20',

    finals:
        'border-purple-300 bg-purple-50/50 dark:bg-purple-950/20',
};

const periodBadge: Record<string, string> = {
    midterm:
        'text-blue-700 border-blue-300',

    finals:
        'text-purple-700 border-purple-300',
};

/*
|--------------------------------------------------------------------------
| Suggested Presets
|--------------------------------------------------------------------------
*/

function presetsFor(
    name: string,
): { label: string; max: number }[] {
    const n = name.toLowerCase();

    if (n.includes('quiz')) {
        return [
            { label: 'Quiz 1', max: 20 },
            { label: 'Quiz 2', max: 20 },
            { label: 'Quiz 3', max: 20 },
        ];
    }

    if (
        n.includes('activ') ||
        n.includes('hands on')
    ) {
        return [
            { label: 'Activity 1', max: 50 },
            { label: 'Activity 2', max: 50 },
        ];
    }

    if (n.includes('assign')) {
        return [
            { label: 'Assignment 1', max: 100 },
            { label: 'Assignment 2', max: 100 },
        ];
    }

    if (
        n.includes('exam') ||
        n.includes('midterm') ||
        n.includes('final')
    ) {
        return [
            { label: 'Written Exam', max: 100 },
            { label: 'Practical Exam', max: 50 },
        ];
    }

    if (n.includes('attend')) {
        return [
            { label: 'Attendance', max: 30 },
        ];
    }

    if (n.includes('project')) {
        return [
            { label: 'Project', max: 100 },
        ];
    }

    if (n.includes('recit')) {
        return [
            { label: 'Recitation 1', max: 20 },
            { label: 'Recitation 2', max: 20 },
        ];
    }

    return [];
}
</script>

<template>
    <Head
        :title="
            section
                ? `Items — ${section.name}`
                : 'Assessment Items'
        "
    />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">

        <!-- Header -->
        <div class="flex items-start gap-3">

            <Button
                variant="ghost"
                size="sm"
                as-child
                class="-ml-2 mt-0.5"
            >
                <Link
                    :href="
                        `/sections/${props.sectionId}/class-record`
                    "
                >
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>

            <div class="flex-1">
                <h1 class="text-xl font-semibold">
                    Assessment Items
                </h1>

                <p
                    v-if="section"
                    class="text-sm text-muted-foreground"
                >
                    {{ section.subject.code }} ·
                    {{ section.name }}
                    — Add quizzes, activities, exams, etc. under
                    each component.
                </p>

                <p
                    v-else
                    class="text-sm text-muted-foreground"
                >
                    Loading section information...
                </p>
            </div>

            <Button
                variant="outline"
                size="sm"
                as-child
            >
                <Link
                    :href="
                        `/sections/${props.sectionId}/class-record`
                    "
                >
                    <ClipboardList
                        class="mr-1.5 h-3.5 w-3.5"
                    />
                    Class Record
                </Link>
            </Button>
        </div>

        <!-- Loading -->
        <div
            v-if="loading"
            class="rounded-xl border p-10 text-center text-sm text-muted-foreground"
        >
            Loading assessment items...
        </div>

        <template v-else>

            <!-- No components -->
            <div
                v-if="components.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                <p class="text-sm">
                    No grading components set up yet.
                </p>

                <Button
                    variant="outline"
                    size="sm"
                    class="mt-3"
                    as-child
                >
                    <Link
                        :href="
                            `/sections/${props.sectionId}/components`
                        "
                    >
                        Set Up Components
                    </Link>
                </Button>
            </div>

            <!-- Components -->
            <div
                v-else
                class="max-w-2xl space-y-4"
            >
                <div
                    v-for="comp in components"
                    :key="comp.id"
                    class="overflow-hidden rounded-xl border"
                    :class="
                        comp.period
                            ? periodColor[comp.period]
                            : ''
                    "
                >

                    <!-- Component Header -->
                    <div
                        class="flex items-center justify-between border-b bg-muted/20 px-4 py-3"
                    >
                        <div>
                            <div class="flex items-center gap-2">

                                <span class="font-semibold">
                                    {{ comp.name }}
                                </span>

                                <Badge
                                    variant="outline"
                                    class="text-xs"
                                    :class="
                                        comp.period
                                            ? periodBadge[comp.period]
                                            : ''
                                    "
                                >
                                    {{
                                        comp.period
                                            ? comp.period
                                                .charAt(0)
                                                .toUpperCase() +
                                              comp.period.slice(1)
                                            : 'General'
                                    }}
                                    ·
                                    {{ comp.weight_percentage }}%
                                </Badge>

                                <Badge
                                    v-if="comp.is_locked"
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    Locked
                                </Badge>
                            </div>

                            <p
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{
                                    itemsByComponent[comp.id]
                                        ?.length ?? 0
                                }}
                                item{{
                                    (
                                        itemsByComponent[comp.id]
                                            ?.length ?? 0
                                    ) !== 1
                                        ? 's'
                                        : ''
                                }}

                                <span
                                    v-if="
                                        itemsByComponent[
                                            comp.id
                                        ]?.some(
                                            (i) => !i.is_enabled,
                                        )
                                    "
                                    class="ml-1 text-orange-500"
                                >
                                    ·
                                    {{
                                        itemsByComponent[
                                            comp.id
                                        ]?.filter(
                                            (i) =>
                                                !i.is_enabled,
                                        ).length
                                    }}
                                    disabled
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Existing Items -->
                    <div
                        v-if="
                            itemsByComponent[comp.id]?.length
                        "
                        class="divide-y"
                    >
                        <div
                            v-for="item in itemsByComponent[
                                comp.id
                            ]"
                            :key="item.id"
                            class="flex items-center gap-3 px-4 py-2.5"
                            :class="
                                !item.is_enabled
                                    ? 'opacity-50'
                                    : ''
                            "
                        >

                            <!-- Edit Mode -->
                            <template
                                v-if="editingId === item.id"
                            >
                                <Input
                                    v-model="editName"
                                    class="h-8 flex-1 text-sm"
                                    placeholder="Item name"
                                />

                                <Input
                                    v-model="editMax"
                                    type="number"
                                    min="1"
                                    class="h-8 w-20 text-center text-sm"
                                />

                                <Button
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    :disabled="
                                        processing === item.id
                                    "
                                    @click="saveEdit(item)"
                                >
                                    Save
                                </Button>

                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="h-8 px-2 text-xs"
                                    @click="cancelEdit"
                                >
                                    ✕
                                </Button>
                            </template>

                            <!-- View Mode -->
                            <template v-else>

                                <div
                                    class="flex min-w-0 flex-1 items-center gap-2"
                                >
                                    <span
                                        class="text-sm font-medium"
                                    >
                                        {{ item.name }}
                                    </span>

                                    <span
                                        class="text-xs text-muted-foreground"
                                    >
                                        / {{ item.max_score }}
                                    </span>

                                    <Badge
                                        v-if="item.assignment_id"
                                        variant="outline"
                                        class="shrink-0 gap-1 border-sky-300 text-xs text-sky-600"
                                        title="Auto-created from assignment"
                                    >
                                        <Link2
                                            class="h-3 w-3"
                                        />
                                        Assignment
                                    </Badge>
                                </div>

                                <!-- Enable / Disable -->
                                <Badge
                                    variant="outline"
                                    class="shrink-0 cursor-pointer select-none text-xs"
                                    :class="
                                        item.is_enabled
                                            ? 'border-green-300 text-green-600 hover:bg-green-50'
                                            : 'text-muted-foreground hover:bg-muted/50'
                                    "
                                    :title="
                                        item.is_enabled
                                            ? 'Click to disable'
                                            : 'Click to enable'
                                    "
                                    @click="toggle(item)"
                                >
                                    {{
                                        item.is_enabled
                                            ? 'On'
                                            : 'Off'
                                    }}
                                </Badge>

                                <!-- Edit -->
                                <Button
                                    v-if="!item.assignment_id"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 w-7 shrink-0 p-0"
                                    :disabled="
                                        processing === item.id
                                    "
                                    @click="startEdit(item)"
                                >
                                    <Pencil
                                        class="h-3.5 w-3.5"
                                    />
                                </Button>

                                <!-- Delete -->
                                <Button
                                    v-if="!item.assignment_id"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 w-7 shrink-0 p-0 text-destructive hover:text-destructive"
                                    :disabled="
                                        processing === item.id
                                    "
                                    @click="remove(item)"
                                >
                                    <Trash2
                                        class="h-3.5 w-3.5"
                                    />
                                </Button>

                            </template>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="px-4 py-3 text-sm text-muted-foreground"
                    >
                        No items yet — add the first one below.
                    </div>

                    <!-- Add Item -->
                    <div
                        v-if="!comp.is_locked"
                        class="space-y-2 border-t bg-muted/10 px-4 py-3"
                    >
                        <div class="flex items-center gap-2">

                            <Input
                                v-model="
                                    getAdd(comp.id).name
                                "
                                class="h-8 flex-1 text-sm"
                                :placeholder="
                                    `New item for ${comp.name} (e.g. Quiz 1)`
                                "
                                @keydown.enter="
                                    addItem(comp)
                                "
                            />

                            <Input
                                v-model="
                                    getAdd(comp.id).max_score
                                "
                                type="number"
                                min="1"
                                class="h-8 w-20 text-center text-sm"
                                placeholder="Max"
                                @keydown.enter="
                                    addItem(comp)
                                "
                            />

                            <Button
                                size="sm"
                                class="h-8 shrink-0"
                                :disabled="
                                    processing === comp.id
                                "
                                @click="addItem(comp)"
                            >
                                <Plus
                                    class="mr-1 h-3.5 w-3.5"
                                />
                                Add
                            </Button>
                        </div>

                        <!-- Inline Error -->
                        <p
                            v-if="
                                getAdd(comp.id).error
                            "
                            class="text-xs text-destructive"
                        >
                            {{ getAdd(comp.id).error }}
                        </p>

                        <!-- Smart Presets -->
                        <div
                            v-if="
                                presetsFor(comp.name).length
                            "
                            class="flex flex-wrap gap-1.5"
                        >
                            <span
                                class="self-center text-xs text-muted-foreground"
                            >
                                Quick add:
                            </span>

                            <button
                                v-for="p in presetsFor(
                                    comp.name,
                                )"
                                :key="p.label"
                                type="button"
                                class="rounded-md border px-2 py-0.5 text-xs transition-colors hover:bg-muted/60"
                                @click="
                                    applyPreset(
                                        comp.id,
                                        p.label,
                                        p.max,
                                    )
                                "
                            >
                                {{ p.label }}

                                <span
                                    class="text-muted-foreground"
                                >
                                    ({{ p.max }})
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Locked -->
                    <div
                        v-else
                        class="border-t bg-muted/10 px-4 py-2.5 text-xs text-muted-foreground"
                    >
                        This component is locked — unlock it in
                        Components to add items.
                    </div>

                </div>
            </div>

        </template>
    </div>
</template>
