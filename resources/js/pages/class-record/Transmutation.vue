<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';
import { Plus, Trash2, ArrowLeft, RefreshCw } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import { showApiToast, showApiError } from '@/lib/flashToast';

type ScaleRow = {
    id?: string;
    min_score: number;
    max_score: number;
    grade: string;
    description: string;
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

type DefaultRow = {
    min: number;
    max: number;
    grade: string;
    description: string;
};

type TransmutationData = {
    section: Section;
    scale: ScaleRow[];
    default_scale: DefaultRow[];
};

type ApiResponse<T> = {
    success: boolean;
    message: string;
    data: T;
};

const props = defineProps<{
    sectionId: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Class Record', href: '#' },
            { title: 'Transmutation', href: '#' },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const section = ref<Section | null>(null);

const rows = ref<ScaleRow[]>([]);
const defaultScale = ref<DefaultRow[]>([]);

const hasCustomScale = ref(false);

const loading = ref(true);
const processing = ref(false);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function mapDefaultRows(): ScaleRow[] {
    return defaultScale.value.map((row) => ({
        min_score: row.min,
        max_score: row.max,
        grade: row.grade,
        description: row.description,
    }));
}

/*
|--------------------------------------------------------------------------
| Load Data
|--------------------------------------------------------------------------
*/

async function loadData() {
    loading.value = true;

    try {
        const response = await axios.get<
            ApiResponse<TransmutationData>
        >(
            `/sections/${props.sectionId}/transmutation/data`,
        );

        const data = response.data.data;

        section.value = data.section;

        defaultScale.value = data.default_scale ?? [];

        if (
            data.scale &&
            Array.isArray(data.scale) &&
            data.scale.length > 0
        ) {
            rows.value = data.scale.map(
                (row: ScaleRow) => ({
                    ...row,
                }),
            );

            hasCustomScale.value = true;
        } else {
            rows.value = mapDefaultRows();
            hasCustomScale.value = false;
        }
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
| Local Default Scale
|--------------------------------------------------------------------------
*/

function loadDefault() {
    rows.value = mapDefaultRows();
}

/*
|--------------------------------------------------------------------------
| Add Row
|--------------------------------------------------------------------------
*/

function addRow() {
    rows.value.push({
        min_score: 0,
        max_score: 0,
        grade: '',
        description: '',
    });
}

/*
|--------------------------------------------------------------------------
| Remove Row
|--------------------------------------------------------------------------
*/

function removeRow(index: number) {
    rows.value.splice(index, 1);
}

/*
|--------------------------------------------------------------------------
| Save Custom Scale
|--------------------------------------------------------------------------
*/

async function save() {
    processing.value = true;

    try {
        const response = await axios.post(
            `/sections/${props.sectionId}/transmutation`,
            {
                rows: rows.value,
            },
        );

        showApiToast(response);

        hasCustomScale.value = true;

        await loadData();
    } catch (error) {
        showApiError(error);
    } finally {
        processing.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Apply Default To Database
|--------------------------------------------------------------------------
*/

async function applyDefault() {
    if (
        !confirm(
            'Apply the default Philippine grading scale? This will replace your current custom scale.',
        )
    ) {
        return;
    }

    processing.value = true;

    try {
        const response = await axios.post(
            `/sections/${props.sectionId}/transmutation/default`,
        );

        showApiToast(response);

        hasCustomScale.value = false;

        await loadData();
    } catch (error) {
        showApiError(error);
    } finally {
        processing.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Reset Custom Scale
|--------------------------------------------------------------------------
*/

async function resetScale() {
    if (
        !confirm(
            'Remove custom scale and use default for this section?',
        )
    ) {
        return;
    }

    processing.value = true;

    try {
        const response = await axios.delete(
            `/sections/${props.sectionId}/transmutation`,
        );

        showApiToast(response);

        hasCustomScale.value = false;

        await loadData();
    } catch (error) {
        showApiError(error);
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Head
        :title="
            section
                ? `Transmutation — ${section.name}`
                : 'Transmutation'
        "
    />

    <div
        class="flex h-full max-w-2xl flex-1 flex-col gap-6 p-4"
    >
        <!-- Header -->
        <div class="flex items-start gap-3">
            <Button
                variant="ghost"
                size="sm"
                as-child
                class="-ml-2 mt-0.5"
            >
                <Link
                    :href="`/sections/${props.sectionId}/class-record`"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>

            <div>
                <h1 class="text-xl font-semibold">
                    Transmutation Scale
                </h1>

                <p
                    v-if="section"
                    class="text-sm text-muted-foreground"
                >
                    {{ section.subject?.code ?? '' }}
                    ·
                    {{ section.name }}
                </p>

                <p
                    v-else
                    class="text-sm text-muted-foreground"
                >
                    Loading section...
                </p>
            </div>
        </div>

        <!-- Loading -->
        <div
            v-if="loading"
            class="rounded-xl border p-10 text-center text-sm text-muted-foreground"
        >
            Loading transmutation scale...
        </div>

        <template v-else>
            <!-- Actions -->
            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="processing"
                    @click="loadDefault"
                >
                    <RefreshCw
                        class="mr-1.5 h-4 w-4"
                    />
                    Load Philippine Default
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    :disabled="processing"
                    @click="applyDefault"
                >
                    Apply Default to DB
                </Button>

                <Button
                    v-if="hasCustomScale"
                    variant="ghost"
                    size="sm"
                    class="text-destructive hover:text-destructive"
                    :disabled="processing"
                    @click="resetScale"
                >
                    Reset to Default
                </Button>
            </div>

            <!-- Scale Editor -->
            <div
                class="overflow-hidden rounded-xl border"
            >
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50"
                    >
                        <tr>
                            <th
                                class="px-3 py-2.5 text-center font-medium text-muted-foreground"
                            >
                                Min %
                            </th>

                            <th
                                class="px-3 py-2.5 text-center font-medium text-muted-foreground"
                            >
                                Max %
                            </th>

                            <th
                                class="px-3 py-2.5 text-center font-medium text-muted-foreground"
                            >
                                Grade
                            </th>

                            <th
                                class="px-3 py-2.5 text-left font-medium text-muted-foreground"
                            >
                                Description
                            </th>

                            <th
                                class="px-3 py-2.5"
                            ></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        <tr
                            v-for="(row, index) in rows"
                            :key="
                                row.id ??
                                `new-${index}`
                            "
                            class="hover:bg-muted/20"
                        >
                            <!-- Min -->
                            <td class="px-2 py-1.5">
                                <Input
                                    v-model.number="
                                        row.min_score
                                    "
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    class="w-20 text-center"
                                />
                            </td>

                            <!-- Max -->
                            <td class="px-2 py-1.5">
                                <Input
                                    v-model.number="
                                        row.max_score
                                    "
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    class="w-20 text-center"
                                />
                            </td>

                            <!-- Grade -->
                            <td class="px-2 py-1.5">
                                <Input
                                    v-model="row.grade"
                                    placeholder="1.00"
                                    class="w-20 text-center font-mono"
                                />
                            </td>

                            <!-- Description -->
                            <td class="px-2 py-1.5">
                                <Input
                                    v-model="
                                        row.description
                                    "
                                    placeholder="e.g. Excellent"
                                    class="w-36"
                                />
                            </td>

                            <!-- Remove -->
                            <td class="px-2 py-1.5">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    :disabled="processing"
                                    @click="
                                        removeRow(index)
                                    "
                                >
                                    <Trash2
                                        class="h-4 w-4"
                                    />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Save Controls -->
            <div class="flex gap-3">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="processing"
                    @click="addRow"
                >
                    <Plus
                        class="mr-1.5 h-4 w-4"
                    />
                    Add Row
                </Button>

                <Button
                    :disabled="processing"
                    @click="save"
                >
                    {{
                        processing
                            ? 'Saving...'
                            : 'Save Scale'
                    }}
                </Button>
            </div>

            <!-- Information -->
            <div
                class="rounded-lg border bg-muted/20 p-3 text-xs text-muted-foreground"
            >
                <strong>
                    Default Philippine Grading Scale
                </strong>
                — 97–100 = 1.00 (Excellent) ...
                75 = 3.00 (Passing) ... &lt;75 = 5.00
                (Failed).

                Scores are matched from highest range
                downward. Custom scale applies to this
                section only.
            </div>
        </template>
    </div>
</template>