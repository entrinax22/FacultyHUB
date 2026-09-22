<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, computed, onMounted } from 'vue';
import { Settings, BarChart2, Download, Lock, FileDown } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { showApiToast, showApiError } from '@/lib/flashToast';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type Student = {
    id: string;
    student_no: string;
    first_name: string;
    last_name: string;
    course: string;
    year_level: number;
};

type AssignmentGrade = {
    score: number;
    max: number;
    pct: number;
    released: boolean;
} | null;

type Row = {
    student: Student;
    enrollment_id: string;
    scores: Record<string, number | null>;
    assignment_grades: Record<string, AssignmentGrade>;
    midterm_grade: number | null;
    finals_grade: number | null;
    total_grade: number | null;
    final_grade: string | null;
    midterm_final_grade: string | null;
    finals_final_grade: string | null;
};

type GradingComponent = {
    id: string;
    name: string;
    weight_percentage: number;
    max_score: number;
    period: string | null;
    is_locked: boolean;
};

type GradingItem = {
    id: string;
    section_id: string;
    component_id: string;
    assignment_id: string | null;
    name: string;
    max_score: number;
    is_enabled: boolean;
    order: number;
};

type Assignment = {
    id: string;
    title: string;
    max_score: number;
    type: string;
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
        school_year?: string;
    } | null;
    faculty?: {
        name: string;
    } | null;
};

type ClassRecordData = {
    section: Section;
    components: GradingComponent[];
    items: GradingItem[];
    assignments: Assignment[];
    midtermWeight: number;
    finalsWeight: number;
    generalWeight: number;
    rows: Row[];
    hasCustomScale: boolean;
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
|
| The page route only provides enough information to render the page.
| The actual class-record data is loaded from the /data endpoint.
|
*/

const props = defineProps<{
    section: Section;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Class Record', href: '#' },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const section = ref<Section>(props.section);

const components = ref<GradingComponent[]>([]);
const items = ref<GradingItem[]>([]);
const assignments = ref<Assignment[]>([]);
const rows = ref<Row[]>([]);

const midtermWeight = ref(0);
const finalsWeight = ref(0);
const generalWeight = ref(0);

const hasCustomScale = ref(false);

const loading = ref(true);
const releasing = ref(false);

const pendingScores = ref<Record<string, Record<string, string>>>({});

const savingScores = ref<Record<string, Record<string, boolean>>>({});

/*
|--------------------------------------------------------------------------
| Load Class Record
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Do not call:
|
| /sections/{sectionId}/class-record
|
| because that is now the Inertia page route.
|
| Axios must call:
|
| /sections/{sectionId}/class-record/data
|
*/

async function loadClassRecord() {
    loading.value = true;

    try {
        const response = await axios.get(
            `/sections/${props.section.id}/class-record/data`,
        );

        const data: ClassRecordData = response.data.data;

        section.value = data.section;
        components.value = data.components ?? [];
        items.value = data.items ?? [];
        assignments.value = data.assignments ?? [];
        rows.value = data.rows ?? [];

        midtermWeight.value = Number(data.midtermWeight ?? 0);

        finalsWeight.value = Number(data.finalsWeight ?? 0);

        generalWeight.value = Number(data.generalWeight ?? 0);

        hasCustomScale.value = Boolean(data.hasCustomScale);
    } catch (error) {
        showApiError(error);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadClassRecord();
});

/*
|--------------------------------------------------------------------------
| Score Helpers
|--------------------------------------------------------------------------
*/

function getDisplayScore(row: Row, item: GradingItem): string {
    const pending = pendingScores.value[row.student.id]?.[item.id];

    if (pending !== undefined) {
        return pending;
    }

    const score = row.scores[item.id];

    return score !== null && score !== undefined ? score.toString() : '';
}

function onInput(studentId: string, itemId: string, value: string) {
    if (!pendingScores.value[studentId]) {
        pendingScores.value[studentId] = {};
    }

    pendingScores.value[studentId][itemId] = value;
}

function setSaving(studentId: string, itemId: string, value: boolean) {
    if (!savingScores.value[studentId]) {
        savingScores.value[studentId] = {};
    }

    savingScores.value[studentId][itemId] = value;
}

function isSaving(studentId: string, itemId: string): boolean {
    return Boolean(savingScores.value[studentId]?.[itemId]);
}

/*
|--------------------------------------------------------------------------
| Save Score
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Save Score
|--------------------------------------------------------------------------
*/

async function saveScore(row: Row, item: GradingItem) {
    const rawValue = pendingScores.value[row.student.id]?.[item.id];

    if (rawValue === undefined) {
        return;
    }

    const trimmedValue = rawValue.trim();

    const score =
        trimmedValue === ''
            ? null
            : Number(trimmedValue);

    // ---------------------------------------------------------
    // Validate score
    // ---------------------------------------------------------

    if (
        score !== null &&
        (
            Number.isNaN(score) ||
            score < 0 ||
            score > Number(item.max_score)
        )
    ) {
        showApiError({
            response: {
                data: {
                    message: `Score must be between 0 and ${item.max_score}.`,
                },
            },
        });

        return;
    }

    // ---------------------------------------------------------
    // Remove pending value before saving
    // ---------------------------------------------------------

    delete pendingScores.value[row.student.id]?.[item.id];

    setSaving(
        row.student.id,
        item.id,
        true,
    );

    try {
        const response = await axios.put(
            '/class-record/item-scores',
            {
                student_id: row.student.id,
                section_id: section.value.id,
                item_id: item.id,
                score,
            },
        );

        showApiToast(response);

        // -----------------------------------------------------
        // IMPORTANT
        //
        // Crypt::encryptString() generates a different encrypted
        // value every time it is called.
        //
        // Therefore, do NOT replace only rows[index].
        //
        // Reload the complete class record so:
        //
        // items.id
        // rows.scores[item.id]
        // components.id
        // assignments.id
        //
        // all come from the same response.
        // -----------------------------------------------------

        await loadClassRecord();
    } catch (error) {
        // -----------------------------------------------------
        // Restore user's input if saving fails
        // -----------------------------------------------------

        pendingScores.value[row.student.id] ??= {};

        pendingScores.value[row.student.id][item.id] =
            rawValue;

        showApiError(error);
    } finally {
        setSaving(
            row.student.id,
            item.id,
            false,
        );
    }
}

/*
|--------------------------------------------------------------------------
| Grade Colors
|--------------------------------------------------------------------------
*/

function gradeColor(value: number | null): string {
    if (value === null) {
        return 'text-muted-foreground';
    }

    if (value >= 75) {
        return 'text-green-600 font-semibold';
    }

    if (value >= 60) {
        return 'text-orange-500 font-semibold';
    }

    return 'text-red-600 font-semibold';
}

function transmutedColor(grade: string | null): string {
    if (!grade) {
        return 'text-muted-foreground';
    }

    return parseFloat(grade) <= 3.0
        ? 'text-green-600 font-bold'
        : 'text-red-600 font-bold';
}

function pctColor(pct: number): string {
    if (pct >= 75) {
        return 'text-green-600';
    }

    if (pct >= 60) {
        return 'text-orange-500';
    }

    return 'text-red-500';
}

/*
|--------------------------------------------------------------------------
| Weight Computation
|--------------------------------------------------------------------------
*/

const weightOk = (weight: number) => Math.abs(weight - 100) < 0.01;

const hasMidtermComponents = computed(() =>
    components.value.some((component) => component.period === 'midterm'),
);

const hasFinalsComponents = computed(() =>
    components.value.some((component) => component.period === 'finals'),
);

const hasGeneralComponents = computed(() =>
    components.value.some((component) => !component.period),
);

const isWeightComplete = computed(() => {
    if (hasMidtermComponents.value && !weightOk(midtermWeight.value)) {
        return false;
    }

    if (hasFinalsComponents.value && !weightOk(finalsWeight.value)) {
        return false;
    }

    if (hasGeneralComponents.value && !weightOk(generalWeight.value)) {
        return false;
    }

    return components.value.length > 0;
});

/*
|--------------------------------------------------------------------------
| Component / Item Grouping
|--------------------------------------------------------------------------
*/

const enabledItems = computed(() =>
    items.value.filter((item) => item.is_enabled !== false),
);

const itemsByComponent = computed(() => {
    const map: Record<string, GradingItem[]> = {};

    for (const component of components.value) {
        map[component.id] = [];
    }

    for (const item of enabledItems.value) {
        if (!map[item.component_id]) {
            map[item.component_id] = [];
        }

        map[item.component_id].push(item);
    }

    for (const key of Object.keys(map)) {
        map[key].sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
    }

    return map;
});

const componentsWithItems = computed(() =>
    components.value.filter(
        (component) => (itemsByComponent.value[component.id]?.length ?? 0) > 0,
    ),
);

const hasPeriods = computed(() =>
    components.value.some(
        (component) =>
            component.period === 'midterm' || component.period === 'finals',
    ),
);

/*
|--------------------------------------------------------------------------
| Release Grades
|--------------------------------------------------------------------------
*/

async function releaseAll() {
    console.log('1. releaseAll() called');

    if (!confirm('Release all component grades to students?')) {
        console.log('2. User cancelled');
        return;
    }

    console.log('3. User confirmed');

    releasing.value = true;

    const url = `/sections/${section.value.id}/class-record/release-all`;

    console.log('4. Section ID:', section.value.id);
    console.log('5. Request URL:', url);

    try {
        console.log('6. BEFORE AXIOS POST');

        const response = await axios.post(url);

        console.log('7. AFTER AXIOS POST');
        console.log('8. Response:', response);
        console.log('9. Response data:', response.data);

        showApiToast(response);

        console.log('10. Loading class record');

        await loadClassRecord();

        console.log('11. Class record loaded');
    } catch (error) {
        console.error('RELEASE ERROR:', error);
        showApiError(error);
    } finally {
        releasing.value = false;
        console.log('12. releaseAll finished');
    }
}

/*
|--------------------------------------------------------------------------
| Utilities
|--------------------------------------------------------------------------
*/

function exportPdf() {
    window.open(`/sections/${section.value.id}/class-record/pdf`, '_blank');
}

const typeLabel: Record<string, string> = {
    essay: 'Essay',
    mcq: 'MCQ',
    code: 'Code',
};

function getItemAverage(itemId: string): string {
    const scores = rows.value
        .map((row) => row.scores[itemId])
        .filter(
            (value): value is number => value !== null && value !== undefined,
        );

    if (!scores.length) {
        return '—';
    }

    return (
        scores.reduce((total, value) => total + value, 0) / scores.length
    ).toFixed(1);
}

function getAssignmentAverage(assignmentId: string): string {
    const percentages = rows.value
        .map((row) => row.assignment_grades[assignmentId]?.pct)
        .filter(
            (value): value is number => value !== null && value !== undefined,
        );

    if (!percentages.length) {
        return '—';
    }

    return (
        (
            percentages.reduce((total, value) => total + value, 0) /
            percentages.length
        ).toFixed(1) + '%'
    );
}

function getGradeAverage(type: 'midterm' | 'finals' | 'total'): string {
    const values = rows.value
        .map((row) => {
            if (type === 'midterm') {
                return row.midterm_grade;
            }

            if (type === 'finals') {
                return row.finals_grade;
            }

            return row.total_grade;
        })
        .filter(
            (value): value is number => value !== null && value !== undefined,
        );

    if (!values.length) {
        return '—';
    }

    return (
        (
            values.reduce((total, value) => total + value, 0) / values.length
        ).toFixed(2) + '%'
    );
}

const passingCount = computed(
    () =>
        rows.value.filter(
            (row) => row.final_grade && parseFloat(row.final_grade) <= 3.0,
        ).length,
);
</script>

<template>
    <Head :title="`Class Record — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex items-start justify-between gap-4 print:hidden">
            <div>
                <h1 class="text-2xl font-semibold">Class Record</h1>

                <p class="text-sm text-muted-foreground">
                    {{ section.subject?.code }}
                    —
                    {{ section.subject?.name }}
                    ·
                    {{ section.name }}
                    ·
                    {{ section.semester?.name }}
                    {{ section.semester?.school_year ?? '' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/components`">
                        <Settings class="mr-1.5 h-4 w-4" />
                        Components
                    </Link>
                </Button>

                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/transmutation`">
                        <BarChart2 class="mr-1.5 h-4 w-4" />
                        Transmutation
                    </Link>
                </Button>

                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/items`"> Items </Link>
                </Button>

                <Button
                    v-if="rows.length > 0"
                    variant="outline"
                    size="sm"
                    :disabled="releasing"
                    @click="releaseAll"
                >
                    <Lock class="mr-1.5 h-4 w-4" />

                    {{ releasing ? 'Releasing...' : 'Release Grades' }}
                </Button>

                <Button variant="outline" size="sm" @click="exportPdf">
                    <FileDown class="mr-1.5 h-4 w-4" />

                    Export PDF
                </Button>
            </div>
        </div>

        <!-- Loading -->
        <div
            v-if="loading"
            class="rounded-xl border p-10 text-center text-sm text-muted-foreground"
        >
            Loading class record...
        </div>

        <template v-else>
            <!-- Weight Warning -->
            <div
                v-if="!isWeightComplete && components.length > 0"
                class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-2 text-sm text-orange-700 print:hidden"
            >
                <span v-if="hasMidtermComponents && !weightOk(midtermWeight)">
                    Midterm weights:
                    {{ midtermWeight.toFixed(1) }}%.
                </span>

                <span
                    v-if="hasFinalsComponents && !weightOk(finalsWeight)"
                    class="ml-2"
                >
                    Finals weights:
                    {{ finalsWeight.toFixed(1) }}%.
                </span>

                <span
                    v-if="hasGeneralComponents && !weightOk(generalWeight)"
                    class="ml-2"
                >
                    General weights:
                    {{ generalWeight.toFixed(1) }}%.
                </span>

                <span class="ml-2">
                    Each group must total 100% for grades to compute correctly.
                </span>

                <Link
                    :href="`/sections/${section.id}/components`"
                    class="ml-1 underline"
                >
                    Fix →
                </Link>
            </div>

            <!-- Period Tip -->
            <div
                v-if="components.length > 0 && !hasPeriods"
                class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm text-blue-700 print:hidden"
            >
                Tip: Tag each component as
                <strong>Midterm</strong> or <strong>Finals</strong> in
                <Link
                    :href="`/sections/${section.id}/components`"
                    class="underline"
                >
                    Components
                </Link>
                to auto-compute Midterm Grade, Finals Grade, and Total.
            </div>

            <!-- No Components -->
            <div
                v-if="components.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground print:hidden"
            >
                <p class="text-sm">No grading components set up yet.</p>

                <Button variant="outline" size="sm" class="mt-3" as-child>
                    <Link :href="`/sections/${section.id}/components`">
                        Set Up Components
                    </Link>
                </Button>
            </div>

            <!-- No Students -->
            <div
                v-else-if="rows.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                <p class="text-sm">No students enrolled in this section.</p>
            </div>

            <!-- Spreadsheet -->
            <div v-else class="overflow-x-auto rounded-xl border">
                <!-- Print Header -->
                <div class="hidden border-b p-4 text-center print:block">
                    <h2 class="text-lg font-bold">CLASS RECORD</h2>

                    <p class="text-sm">
                        {{ section.subject?.name }}
                        ({{ section.subject?.code }}) ·
                        {{ section.name }}
                    </p>

                    <p class="text-sm">
                        {{ section.semester?.name }}
                        {{ section.semester?.school_year ?? '' }}
                        · Faculty:
                        {{ section.faculty?.name ?? '—' }}
                    </p>
                </div>

                <table class="w-full min-w-max text-sm">
                    <thead class="border-b bg-muted/50">
                        <!-- Group Header -->
                        <tr>
                            <th
                                class="sticky left-0 z-10 bg-muted/50 px-4 py-2 text-left font-medium text-muted-foreground"
                            >
                                #
                            </th>

                            <th
                                class="sticky left-8 z-10 bg-muted/50 px-4 py-2 text-left font-medium whitespace-nowrap text-muted-foreground"
                            >
                                Student
                            </th>

                            <!-- Components -->
                            <th
                                v-for="comp in componentsWithItems"
                                :key="comp.id"
                                :colspan="itemsByComponent[comp.id].length"
                                class="px-3 py-2 text-center font-medium whitespace-nowrap text-muted-foreground"
                            >
                                <div
                                    class="flex items-center justify-center gap-1"
                                >
                                    <span>
                                        {{ comp.name }}
                                    </span>

                                    <Badge
                                        v-if="comp.period"
                                        variant="outline"
                                        class="px-1 py-0 text-[10px] capitalize"
                                    >
                                        {{ comp.period }}
                                    </Badge>
                                </div>

                                <div class="text-xs font-normal opacity-70">
                                    {{ comp.weight_percentage }}%
                                </div>
                            </th>

                            <!-- Assignments -->
                            <th
                                v-if="assignments.length > 0"
                                :colspan="assignments.length"
                                class="border-l px-3 py-2 text-center font-medium whitespace-nowrap text-muted-foreground"
                            >
                                Assignments
                            </th>

                            <!-- Summary -->
                            <th
                                v-if="hasPeriods"
                                class="border-l bg-blue-50/50 px-4 py-2 text-center font-medium whitespace-nowrap text-muted-foreground"
                            >
                                Midterm
                            </th>

                            <th
                                v-if="hasPeriods"
                                class="bg-blue-50/50 px-4 py-2 text-center font-medium whitespace-nowrap text-muted-foreground"
                            >
                                Finals
                            </th>

                            <th
                                class="px-4 py-2 text-center font-medium whitespace-nowrap text-muted-foreground"
                                :class="hasPeriods ? 'bg-blue-50/50' : ''"
                            >
                                Total %
                            </th>

                            <th
                                class="bg-primary/5 px-4 py-2 text-center font-medium whitespace-nowrap text-muted-foreground"
                            >
                                Final Grade
                            </th>
                        </tr>

                        <!-- Item Header -->
                        <tr class="border-t bg-muted/30">
                            <th
                                class="sticky left-0 z-10 bg-muted/30 px-4 py-1.5"
                            ></th>

                            <th
                                class="sticky left-8 z-10 bg-muted/30 px-4 py-1.5 text-left text-xs font-medium text-muted-foreground"
                            >
                                Items
                            </th>

                            <template
                                v-for="comp in componentsWithItems"
                                :key="comp.id"
                            >
                                <th
                                    v-for="item in itemsByComponent[comp.id]"
                                    :key="item.id"
                                    class="px-2 py-1.5 text-center text-xs font-medium whitespace-nowrap text-muted-foreground"
                                >
                                    <div>
                                        {{ item.name }}
                                    </div>

                                    <div
                                        class="text-[10px] font-normal opacity-70"
                                    >
                                        /
                                        {{ item.max_score }}
                                    </div>
                                </th>
                            </template>

                            <template v-if="assignments.length > 0">
                                <th
                                    v-for="assignment in assignments"
                                    :key="assignment.id"
                                    class="border-l px-2 py-1.5 text-center text-xs font-medium whitespace-nowrap text-muted-foreground"
                                >
                                    <div class="max-w-[100px] truncate">
                                        {{ assignment.title }}
                                    </div>

                                    <div
                                        class="text-[10px] font-normal opacity-70"
                                    >
                                        {{
                                            typeLabel[assignment.type] ??
                                            assignment.type
                                        }}
                                        /
                                        {{ assignment.max_score }}
                                    </div>
                                </th>
                            </template>

                            <th
                                v-if="hasPeriods"
                                class="border-l bg-blue-50/30 px-4 py-1.5"
                            ></th>

                            <th
                                v-if="hasPeriods"
                                class="bg-blue-50/30 px-4 py-1.5"
                            ></th>

                            <th
                                class="px-4 py-1.5"
                                :class="hasPeriods ? 'bg-blue-50/30' : ''"
                            ></th>

                            <th class="bg-primary/5 px-4 py-1.5"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        <tr
                            v-for="(row, index) in rows"
                            :key="row.student.id"
                            class="transition-colors hover:bg-muted/20"
                        >
                            <!-- Number -->
                            <td
                                class="sticky left-0 z-10 bg-card px-4 py-2 text-center text-xs text-muted-foreground"
                            >
                                {{ index + 1 }}
                            </td>

                            <!-- Student -->
                            <td
                                class="sticky left-8 z-10 bg-card px-4 py-2 whitespace-nowrap"
                            >
                                <p class="font-medium">
                                    {{ row.student.last_name }},
                                    {{ row.student.first_name }}
                                </p>

                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ row.student.student_no }}
                                </p>
                            </td>

                            <!-- Scores -->
                            <template
                                v-for="comp in componentsWithItems"
                                :key="comp.id"
                            >
                                <td
                                    v-for="item in itemsByComponent[comp.id]"
                                    :key="item.id"
                                    class="px-2 py-1.5 text-center"
                                >
                                    <input
                                        type="number"
                                        min="0"
                                        :max="item.max_score"
                                        step="0.5"
                                        :value="getDisplayScore(row, item)"
                                        :disabled="
                                            comp.is_locked ||
                                            isSaving(row.student.id, item.id)
                                        "
                                        class="w-16 rounded-md border border-input bg-transparent px-2 py-1 text-center text-sm focus:ring-1 focus:ring-ring focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 print:border-0"
                                        placeholder="—"
                                        @input="
                                            onInput(
                                                row.student.id,
                                                item.id,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).value,
                                            )
                                        "
                                        @blur="saveScore(row, item)"
                                        @keydown.enter="
                                            saveScore(row, item);
                                            (
                                                $event.target as HTMLInputElement
                                            ).blur();
                                        "
                                    />
                                </td>
                            </template>

                            <!-- Assignment Grades -->
                            <template v-if="assignments.length > 0">
                                <td
                                    v-for="assignment in assignments"
                                    :key="assignment.id"
                                    class="border-l px-3 py-1.5 text-center"
                                >
                                    <template
                                        v-if="
                                            row.assignment_grades[assignment.id]
                                        "
                                    >
                                        <span
                                            :class="
                                                pctColor(
                                                    row.assignment_grades[
                                                        assignment.id
                                                    ]!.pct,
                                                )
                                            "
                                            class="text-sm font-medium"
                                        >
                                            {{
                                                row.assignment_grades[
                                                    assignment.id
                                                ]!.pct
                                            }}%
                                        </span>

                                        <div
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            {{
                                                row.assignment_grades[
                                                    assignment.id
                                                ]!.score
                                            }}
                                            /
                                            {{
                                                row.assignment_grades[
                                                    assignment.id
                                                ]!.max
                                            }}
                                        </div>
                                    </template>

                                    <span v-else class="text-muted-foreground">
                                        —
                                    </span>
                                </td>
                            </template>

                            <!-- Midterm -->
                            <td
                                v-if="hasPeriods"
                                class="border-l bg-blue-50/20 px-4 py-2 text-center"
                                :class="gradeColor(row.midterm_grade)"
                            >
                                <template v-if="row.midterm_grade !== null">
                                    <div class="font-semibold">
                                        {{ row.midterm_final_grade }}
                                    </div>

                                    <div class="text-xs text-muted-foreground">
                                        {{ row.midterm_grade.toFixed(2) }}%
                                    </div>
                                </template>

                                <template v-else> — </template>
                            </td>

                            <!-- Finals -->
                            <td
                                v-if="hasPeriods"
                                class="bg-blue-50/20 px-4 py-2 text-center"
                                :class="gradeColor(row.finals_grade)"
                            >
                                <template v-if="row.finals_grade !== null">
                                    <div class="font-semibold">
                                        {{ row.finals_final_grade }}
                                    </div>

                                    <div class="text-xs text-muted-foreground">
                                        {{ row.finals_grade.toFixed(2) }}%
                                    </div>
                                </template>

                                <template v-else> — </template>
                            </td>

                            <!-- Total -->
                            <td
                                class="px-4 py-2 text-center"
                                :class="[
                                    gradeColor(row.total_grade),
                                    hasPeriods ? 'bg-blue-50/20' : '',
                                ]"
                            >
                                {{
                                    row.total_grade !== null
                                        ? row.total_grade.toFixed(2) + '%'
                                        : '—'
                                }}
                            </td>

                            <!-- Final Grade -->
                            <td
                                class="bg-primary/5 px-4 py-2 text-center"
                                :class="transmutedColor(row.final_grade)"
                            >
                                {{ row.final_grade ?? '—' }}
                            </td>
                        </tr>
                    </tbody>

                    <!-- Summary -->
                    <tfoot class="border-t bg-muted/30">
                        <tr>
                            <td
                                colspan="2"
                                class="sticky left-0 bg-muted/30 px-4 py-2 text-xs font-medium text-muted-foreground"
                            >
                                Class Average
                            </td>

                            <!-- Item averages -->
                            <template
                                v-for="comp in componentsWithItems"
                                :key="comp.id"
                            >
                                <td
                                    v-for="item in itemsByComponent[comp.id]"
                                    :key="item.id"
                                    class="px-2 py-2 text-center text-xs text-muted-foreground"
                                >
                                    {{ getItemAverage(item.id) }}
                                </td>
                            </template>

                            <!-- Assignment averages -->
                            <template v-if="assignments.length > 0">
                                <td
                                    v-for="assignment in assignments"
                                    :key="assignment.id"
                                    class="border-l px-3 py-2 text-center text-xs text-muted-foreground"
                                >
                                    {{ getAssignmentAverage(assignment.id) }}
                                </td>
                            </template>

                            <!-- Midterm average -->
                            <td
                                v-if="hasPeriods"
                                class="border-l px-4 py-2 text-center text-xs text-muted-foreground"
                            >
                                {{ getGradeAverage('midterm') }}
                            </td>

                            <!-- Finals average -->
                            <td
                                v-if="hasPeriods"
                                class="px-4 py-2 text-center text-xs text-muted-foreground"
                            >
                                {{ getGradeAverage('finals') }}
                            </td>

                            <!-- Total average -->
                            <td
                                class="px-4 py-2 text-center text-xs text-muted-foreground"
                            >
                                {{ getGradeAverage('total') }}
                            </td>

                            <!-- Passing -->
                            <td
                                class="bg-primary/5 px-4 py-2 text-center text-xs text-muted-foreground"
                            >
                                {{ passingCount }} /
                                {{ rows.length }}
                                passing
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </template>

        <p class="text-xs text-muted-foreground print:hidden">
            Enter scores per item · Assignment grades are pulled automatically
            from submissions · Tag components as Midterm/Finals to split the
            grade calculation
        </p>
    </div>
</template>

<style scoped>
@media print {
    table {
        font-size: 11px;
    }

    input {
        border: 1px solid #ccc !important;
    }
}
</style>
