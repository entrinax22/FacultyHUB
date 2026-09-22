<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

import {
    Plus,
    Pencil,
    Trash2,
    Lock,
    Unlock,
    AlertTriangle,
    Check,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

type Component = {
    id: string;
    name: string;
    weight_percentage: number;
    max_score: number;
    order: number;
    period: string | null;
    is_locked: boolean;
};

type Section = {
    id: string;
    name: string;
    subject: {
        id?: string;
        code: string;
        name: string;
    } | null;
    semester: {
        id?: string;
        name: string;
    } | null;
};

type ApiData = {
    section: Section;
    components: Component[];
};

type ApiResponse = {
    success: boolean;
    message: string;
    data: ApiData;
};

type MutationResponse = {
    success: boolean;
    message: string;
    data?: {
        component?: Component;
    };
};

type FormErrors = {
    name?: string;
    weight_percentage?: string;
    max_score?: string;
    period?: string;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Grading Components', href: '#' },
        ],
    },
});

const props = defineProps<{
    sectionId: string;
}>();

const section = ref<Section | null>(null);
const components = ref<Component[]>([]);

const loading = ref(true);
const error = ref<string | null>(null);

const addForm = ref({
    name: '',
    weight_percentage: '',
    max_score: '100',
    period: '',
});

const editForm = ref({
    name: '',
    weight_percentage: '',
    max_score: '100',
    period: '',
});

const addErrors = ref<FormErrors>({});
const editErrors = ref<FormErrors>({});

const editingId = ref<string | null>(null);

const adding = ref(false);
const updating = ref(false);
const deletingId = ref<string | null>(null);
const togglingId = ref<string | null>(null);

/**
 * Load grading components from Axios data endpoint.
 */
async function loadComponents() {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get<ApiResponse>(
            `/sections/${props.sectionId}/components/data`,
        );

        if (response.data.success) {
            section.value = response.data.data.section;
            components.value = response.data.data.components;
        } else {
            error.value =
                response.data.message ||
                'Failed to load grading components.';
        }
    } catch (err) {
        console.error('LOAD COMPONENTS ERROR:', err);

        error.value =
            axios.isAxiosError(err)
                ? err.response?.data?.message ||
                  'Failed to load grading components. Please try again.'
                : 'Failed to load grading components. Please try again.';
    } finally {
        loading.value = false;
    }
}

/**
 * Clear add form validation errors.
 */
function clearAddErrors() {
    addErrors.value = {};
}

/**
 * Clear edit form validation errors.
 */
function clearEditErrors() {
    editErrors.value = {};
}

/**
 * Start editing a component.
 */
function startEdit(component: Component) {
    editingId.value = component.id;

    editForm.value = {
        name: component.name,
        weight_percentage:
            component.weight_percentage.toString(),
        max_score: String(component.max_score ?? 100),
        period: component.period ?? '',
    };

    clearEditErrors();
}

/**
 * Cancel component editing.
 */
function cancelEdit() {
    editingId.value = null;

    editForm.value = {
        name: '',
        weight_percentage: '',
        max_score: '100',
        period: '',
    };

    clearEditErrors();
}

/**
 * Handle Laravel validation errors.
 */
function handleValidationErrors(
    error: unknown,
    target: 'add' | 'edit',
): boolean {
    if (
        axios.isAxiosError(error) &&
        error.response?.status === 422
    ) {
        const errors = error.response.data?.errors ?? {};

        const formErrors: FormErrors = {
            name: errors.name?.[0],
            weight_percentage:
                errors.weight_percentage?.[0],
            max_score: errors.max_score?.[0],
            period: errors.period?.[0],
        };

        if (target === 'add') {
            addErrors.value = formErrors;
        } else {
            editErrors.value = formErrors;
        }

        return true;
    }

    return false;
}

/**
 * Add a new grading component.
 */
async function addComponent() {
    clearAddErrors();

    if (!addForm.value.name.trim()) {
        addErrors.value.name =
            'Component name is required.';
        return;
    }

    adding.value = true;

    try {
        const response =
            await axios.post<MutationResponse>(
                `/sections/${props.sectionId}/components`,
                {
                    name: addForm.value.name,
                    weight_percentage:
                        addForm.value.weight_percentage,
                    max_score: addForm.value.max_score,
                    period:
                        addForm.value.period || null,
                },
            );

        showApiToast(response);

        const newComponent =
            response.data.data?.component;

        if (newComponent) {
            components.value.push(newComponent);

            components.value.sort(
                (a, b) => a.order - b.order,
            );
        } else {
            await loadComponents();
        }

        addForm.value = {
            name: '',
            weight_percentage: '',
            max_score: '100',
            period: '',
        };
    } catch (error) {
        if (!handleValidationErrors(error, 'add')) {
            showApiError(error);
        }
    } finally {
        adding.value = false;
    }
}

/**
 * Save an edited component.
 */
async function saveEdit(componentId: string) {
    clearEditErrors();

    updating.value = true;

    try {
        const response =
            await axios.put<MutationResponse>(
                `/components/${componentId}`,
                {
                    name: editForm.value.name,
                    weight_percentage:
                        editForm.value.weight_percentage,
                    max_score: editForm.value.max_score,
                    period:
                        editForm.value.period || null,
                },
            );

        showApiToast(response);

        const updatedComponent =
            response.data.data?.component;

        if (updatedComponent) {
            const index =
                components.value.findIndex(
                    component =>
                        component.id === componentId,
                );

            if (index !== -1) {
                components.value[index] =
                    updatedComponent;
            }
        } else {
            await loadComponents();
        }

        cancelEdit();
    } catch (error) {
        if (!handleValidationErrors(error, 'edit')) {
            showApiError(error);
        }
    } finally {
        updating.value = false;
    }
}

/**
 * Delete a grading component.
 */
async function deleteComponent(
    id: string,
    name: string,
) {
    if (
        !confirm(
            `Remove "${name}"? All grades for this component will be deleted.`,
        )
    ) {
        return;
    }

    deletingId.value = id;

    try {
        const response =
            await axios.delete<MutationResponse>(
                `/components/${id}`,
            );

        showApiToast(response);

        components.value =
            components.value.filter(
                component => component.id !== id,
            );

        if (editingId.value === id) {
            cancelEdit();
        }
    } catch (error) {
        showApiError(error);
    } finally {
        deletingId.value = null;
    }
}

/**
 * Toggle component lock state.
 */
async function toggleLock(id: string) {
    togglingId.value = id;

    try {
        const response =
            await axios.post<MutationResponse>(
                `/components/${id}/toggle-lock`,
            );

        showApiToast(response);

        const updatedComponent =
            response.data.data?.component;

        if (updatedComponent) {
            const index =
                components.value.findIndex(
                    component =>
                        component.id === id,
                );

            if (index !== -1) {
                components.value[index] =
                    updatedComponent;
            }
        } else {
            await loadComponents();
        }

        if (editingId.value === id) {
            cancelEdit();
        }
    } catch (error) {
        showApiError(error);
    } finally {
        togglingId.value = null;
    }
}

/**
 * Load initial data.
 */
onMounted(() => {
    loadComponents();
});

/**
 * Check whether a period exists.
 */
const hasMidterm = computed(() =>
    components.value.some(
        component => component.period === 'midterm',
    ),
);

const hasFinals = computed(() =>
    components.value.some(
        component => component.period === 'finals',
    ),
);

const hasGeneral = computed(() =>
    components.value.some(
        component => !component.period,
    ),
);

/**
 * Calculate period weights.
 */
const midtermWeight = computed(() =>
    components.value
        .filter(
            component =>
                component.period === 'midterm',
        )
        .reduce(
            (total, component) =>
                total +
                Number(component.weight_percentage),
            0,
        ),
);

const finalsWeight = computed(() =>
    components.value
        .filter(
            component =>
                component.period === 'finals',
        )
        .reduce(
            (total, component) =>
                total +
                Number(component.weight_percentage),
            0,
        ),
);

const generalWeight = computed(() =>
    components.value
        .filter(component => !component.period)
        .reduce(
            (total, component) =>
                total +
                Number(component.weight_percentage),
            0,
        ),
);

/**
 * Check if weight reaches exactly 100%.
 */
function weightComplete(weight: number) {
    return Math.abs(weight - 100) < 0.01;
}

/**
 * Get remaining weight.
 */
function weightRemaining(weight: number) {
    return Math.max(0, 100 - weight).toFixed(2);
}

/**
 * Quick presets.
 */
const presets = [
    { name: 'Quizzes', w: 30 },
    { name: 'Assignments', w: 30 },
    { name: 'Attendance', w: 10 },
    { name: 'Midterm Exam', w: 15 },
    { name: 'Final Exam', w: 15 },
];

/**
 * Apply quick preset.
 */
function applyPreset(
    name: string,
    weight: number,
) {
    addForm.value.name = name;
    addForm.value.weight_percentage =
        weight.toString();
}
</script>

<template>
    <Head
        :title="
            `Grading Components${
                section ? ` — ${section.name}` : ''
            }`
        "
    />

    <div
        class="flex h-full flex-1 flex-col gap-6 p-4 max-w-2xl"
    >
        <!-- Header -->
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">
                    Grading Components
                </h1>

                <p
                    v-if="section"
                    class="text-sm text-muted-foreground"
                >
                    {{ section.subject?.code }} ·
                    {{ section.name }} ·
                    {{ section.semester?.name }}
                </p>

                <p
                    v-else-if="loading"
                    class="text-sm text-muted-foreground"
                >
                    Loading section...
                </p>
            </div>

            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="
                            `/sections/${props.sectionId}/items`
                        "
                    >
                        Items
                    </Link>
                </Button>

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
                        View Class Record
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Error -->
        <div
            v-if="error"
            class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
        >
            {{ error }}
        </div>

        <!-- Loading -->
        <div
            v-if="loading"
            class="rounded-xl border p-8 text-center text-sm text-muted-foreground"
        >
            Loading grading components...
        </div>

        <template v-else>
            <!-- Weight Progress -->
            <div class="space-y-4 rounded-xl border p-4">
                <p class="text-sm font-medium">
                    Grade Weights
                </p>

                <p
                    class="-mt-2 text-xs text-muted-foreground"
                >
                    Each period (Midterm / Finals) is an
                    independent 100% grading sheet.
                    General components share their own
                    100%.
                </p>

                <!-- Midterm -->
                <div
                    v-if="hasMidterm"
                    class="space-y-1.5"
                >
                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="font-medium text-blue-600"
                        >
                            Midterm
                        </span>

                        <span
                            :class="
                                weightComplete(
                                    midtermWeight,
                                )
                                    ? 'font-semibold text-green-600'
                                    : midtermWeight > 100
                                      ? 'font-semibold text-red-600'
                                      : 'text-orange-500'
                            "
                        >
                            {{ midtermWeight.toFixed(2) }}%
                            / 100%
                        </span>
                    </div>

                    <div
                        class="h-2 w-full overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full transition-all"
                            :class="
                                weightComplete(
                                    midtermWeight,
                                )
                                    ? 'bg-green-500'
                                    : midtermWeight > 100
                                      ? 'bg-red-500'
                                      : 'bg-blue-500'
                            "
                            :style="{
                                width: `${Math.min(
                                    midtermWeight,
                                    100,
                                )}%`,
                            }"
                        />
                    </div>

                    <p
                        v-if="
                            !weightComplete(
                                midtermWeight,
                            )
                        "
                        class="flex items-center gap-1 text-xs"
                        :class="
                            midtermWeight > 100
                                ? 'text-red-500'
                                : 'text-orange-500'
                        "
                    >
                        <AlertTriangle class="h-3 w-3" />

                        <template
                            v-if="midtermWeight > 100"
                        >
                            Weight exceeds 100%.
                        </template>

                        <template v-else>
                            {{
                                weightRemaining(
                                    midtermWeight,
                                )
                            }}%
                            remaining
                        </template>
                    </p>

                    <p
                        v-else
                        class="flex items-center gap-1 text-xs text-green-600"
                    >
                        <Check class="h-3 w-3" />
                        Complete
                    </p>
                </div>

                <!-- Finals -->
                <div
                    v-if="hasFinals"
                    class="space-y-1.5"
                >
                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="font-medium text-purple-600"
                        >
                            Finals
                        </span>

                        <span
                            :class="
                                weightComplete(
                                    finalsWeight,
                                )
                                    ? 'font-semibold text-green-600'
                                    : finalsWeight > 100
                                      ? 'font-semibold text-red-600'
                                      : 'text-orange-500'
                            "
                        >
                            {{ finalsWeight.toFixed(2) }}%
                            / 100%
                        </span>
                    </div>

                    <div
                        class="h-2 w-full overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full transition-all"
                            :class="
                                weightComplete(
                                    finalsWeight,
                                )
                                    ? 'bg-green-500'
                                    : finalsWeight > 100
                                      ? 'bg-red-500'
                                      : 'bg-purple-500'
                            "
                            :style="{
                                width: `${Math.min(
                                    finalsWeight,
                                    100,
                                )}%`,
                            }"
                        />
                    </div>

                    <p
                        v-if="
                            !weightComplete(
                                finalsWeight,
                            )
                        "
                        class="flex items-center gap-1 text-xs"
                        :class="
                            finalsWeight > 100
                                ? 'text-red-500'
                                : 'text-orange-500'
                        "
                    >
                        <AlertTriangle class="h-3 w-3" />

                        <template
                            v-if="finalsWeight > 100"
                        >
                            Weight exceeds 100%.
                        </template>

                        <template v-else>
                            {{
                                weightRemaining(
                                    finalsWeight,
                                )
                            }}%
                            remaining
                        </template>
                    </p>

                    <p
                        v-else
                        class="flex items-center gap-1 text-xs text-green-600"
                    >
                        <Check class="h-3 w-3" />
                        Complete
                    </p>
                </div>

                <!-- General -->
                <div
                    v-if="hasGeneral"
                    class="space-y-1.5"
                >
                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="font-medium text-muted-foreground"
                        >
                            General
                        </span>

                        <span
                            :class="
                                weightComplete(
                                    generalWeight,
                                )
                                    ? 'font-semibold text-green-600'
                                    : generalWeight > 100
                                      ? 'font-semibold text-red-600'
                                      : 'text-orange-500'
                            "
                        >
                            {{ generalWeight.toFixed(2) }}%
                            / 100%
                        </span>
                    </div>

                    <div
                        class="h-2 w-full overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full transition-all"
                            :class="
                                weightComplete(
                                    generalWeight,
                                )
                                    ? 'bg-green-500'
                                    : generalWeight > 100
                                      ? 'bg-red-500'
                                      : 'bg-primary'
                            "
                            :style="{
                                width: `${Math.min(
                                    generalWeight,
                                    100,
                                )}%`,
                            }"
                        />
                    </div>

                    <p
                        v-if="
                            !weightComplete(
                                generalWeight,
                            )
                        "
                        class="flex items-center gap-1 text-xs"
                        :class="
                            generalWeight > 100
                                ? 'text-red-500'
                                : 'text-orange-500'
                        "
                    >
                        <AlertTriangle class="h-3 w-3" />

                        <template
                            v-if="generalWeight > 100"
                        >
                            Weight exceeds 100%.
                        </template>

                        <template v-else>
                            {{
                                weightRemaining(
                                    generalWeight,
                                )
                            }}%
                            remaining
                        </template>
                    </p>

                    <p
                        v-else
                        class="flex items-center gap-1 text-xs text-green-600"
                    >
                        <Check class="h-3 w-3" />
                        Complete
                    </p>
                </div>

                <p
                    v-if="
                        !hasMidterm &&
                        !hasFinals &&
                        !hasGeneral
                    "
                    class="text-xs text-muted-foreground"
                >
                    Add components below to start building
                    your grading sheet.
                </p>
            </div>

            <!-- Components List -->
            <div class="space-y-2">
                <div
                    v-if="components.length === 0"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    No components yet. Add your first grading
                    component below.
                </div>

                <div
                    v-for="comp in components"
                    :key="comp.id"
                    class="rounded-xl border p-4"
                >
                    <!-- Edit -->
                    <div
                        v-if="editingId === comp.id"
                        class="flex flex-wrap items-end gap-3"
                    >
                        <div
                            class="grid min-w-32 flex-1 gap-1.5"
                        >
                            <Label class="text-xs">
                                Name
                            </Label>

                            <Input
                                v-model="editForm.name"
                                placeholder="e.g. Quizzes"
                            />

                            <InputError
                                :message="editErrors.name"
                            />
                        </div>

                        <div class="grid w-28 gap-1.5">
                            <Label class="text-xs">
                                Weight %
                            </Label>

                            <Input
                                type="number"
                                v-model="
                                    editForm.weight_percentage
                                "
                                min="0.01"
                                max="100"
                                step="0.01"
                            />

                            <InputError
                                :message="
                                    editErrors.weight_percentage
                                "
                            />
                        </div>

                        <div class="grid w-28 gap-1.5">
                            <Label class="text-xs">
                                Max Score
                            </Label>

                            <Input
                                type="number"
                                v-model="editForm.max_score"
                                min="1"
                                step="1"
                            />

                            <InputError
                                :message="
                                    editErrors.max_score
                                "
                            />
                        </div>

                        <div class="grid w-28 gap-1.5">
                            <Label class="text-xs">
                                Period
                            </Label>

                            <select
                                v-model="editForm.period"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="">
                                    None
                                </option>

                                <option value="midterm">
                                    Midterm
                                </option>

                                <option value="finals">
                                    Finals
                                </option>
                            </select>

                            <InputError
                                :message="editErrors.period"
                            />
                        </div>

                        <div class="flex gap-1.5">
                            <Button
                                size="sm"
                                :disabled="updating"
                                @click="
                                    saveEdit(comp.id)
                                "
                            >
                                {{
                                    updating
                                        ? 'Saving...'
                                        : 'Save'
                                }}
                            </Button>

                            <Button
                                size="sm"
                                variant="ghost"
                                :disabled="updating"
                                @click="cancelEdit"
                            >
                                Cancel
                            </Button>
                        </div>
                    </div>

                    <!-- Display -->
                    <div
                        v-else
                        class="flex items-center gap-3"
                    >
                        <div class="flex-1">
                            <div
                                class="flex items-center gap-2"
                            >
                                <p class="font-medium">
                                    {{ comp.name }}
                                </p>

                                <Badge
                                    v-if="comp.period"
                                    variant="outline"
                                    class="text-xs capitalize"
                                >
                                    {{ comp.period }}
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
                                class="text-sm text-muted-foreground"
                            >
                                {{ comp.weight_percentage }}%
                                weight · scores out of
                                {{ comp.max_score }}
                            </p>
                        </div>

                        <div
                            class="h-2 w-24 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{
                                    width: `${Math.min(
                                        Number(
                                            comp.weight_percentage,
                                        ),
                                        100,
                                    )}%`,
                                }"
                            />
                        </div>

                        <div class="flex gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                :title="
                                    comp.is_locked
                                        ? 'Unlock'
                                        : 'Lock'
                                "
                                :disabled="
                                    togglingId === comp.id
                                "
                                @click="
                                    toggleLock(comp.id)
                                "
                            >
                                <Lock
                                    v-if="!comp.is_locked"
                                    class="h-4 w-4"
                                />

                                <Unlock
                                    v-else
                                    class="h-4 w-4"
                                />
                            </Button>

                            <Button
                                v-if="!comp.is_locked"
                                variant="ghost"
                                size="sm"
                                @click="startEdit(comp)"
                            >
                                <Pencil
                                    class="h-4 w-4"
                                />
                            </Button>

                            <Button
                                v-if="!comp.is_locked"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                :disabled="
                                    deletingId === comp.id
                                "
                                @click="
                                    deleteComponent(
                                        comp.id,
                                        comp.name,
                                    )
                                "
                            >
                                <Trash2
                                    class="h-4 w-4"
                                />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Component -->
            <div class="space-y-3 rounded-xl border p-4">
                <p class="text-sm font-medium">
                    Add Component
                </p>

                <div
                    class="flex flex-wrap items-end gap-3"
                >
                    <div
                        class="grid min-w-32 flex-1 gap-1.5"
                    >
                        <Label
                            for="name"
                            class="text-xs"
                        >
                            Component Name
                        </Label>

                        <Input
                            id="name"
                            v-model="addForm.name"
                            placeholder="e.g. Quizzes, Midterm Exam"
                        />

                        <InputError
                            :message="addErrors.name"
                        />
                    </div>

                    <div class="grid w-32 gap-1.5">
                        <Label
                            for="weight"
                            class="text-xs"
                        >
                            Weight %
                        </Label>

                        <Input
                            id="weight"
                            type="number"
                            v-model="
                                addForm.weight_percentage
                            "
                            min="0.01"
                            max="100"
                            step="0.01"
                            placeholder="30"
                        />

                        <InputError
                            :message="
                                addErrors.weight_percentage
                            "
                        />
                    </div>

                    <div class="grid w-28 gap-1.5">
                        <Label
                            for="max"
                            class="text-xs"
                        >
                            Max
                        </Label>

                        <Input
                            id="max"
                            type="number"
                            v-model="addForm.max_score"
                            min="1"
                            step="1"
                            placeholder="100"
                        />

                        <InputError
                            :message="addErrors.max_score"
                        />
                    </div>

                    <div class="grid w-28 gap-1.5">
                        <Label class="text-xs">
                            Period
                        </Label>

                        <select
                            v-model="addForm.period"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option
                                value=""
                                class="bg-white text-black"
                            >
                                None
                            </option>

                            <option
                                value="midterm"
                                class="bg-white text-black"
                            >
                                Midterm
                            </option>

                            <option
                                value="finals"
                                class="bg-white text-black"
                            >
                                Finals
                            </option>
                        </select>

                        <InputError
                            :message="addErrors.period"
                        />
                    </div>

                    <Button
                        :disabled="adding"
                        @click="addComponent"
                    >
                        <Plus
                            class="mr-1.5 h-4 w-4"
                        />

                        {{
                            adding
                                ? 'Adding...'
                                : 'Add'
                        }}
                    </Button>
                </div>

                <!-- Quick Presets -->
                <div
                    class="flex flex-wrap gap-2 pt-1"
                >
                    <p
                        class="w-full text-xs text-muted-foreground"
                    >
                        Quick presets:
                    </p>

                    <button
                        v-for="preset in presets"
                        :key="preset.name"
                        type="button"
                        class="rounded-md border px-2 py-1 text-xs transition-colors hover:bg-muted"
                        @click="
                            applyPreset(
                                preset.name,
                                preset.w,
                            )
                        "
                    >
                        {{ preset.name }}
                        ({{ preset.w }}%)
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
