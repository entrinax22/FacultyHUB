<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Settings, BarChart2, Download, Lock } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Student = { id: number; student_no: string; first_name: string; last_name: string; course: string; year_level: number };
type AssignmentGrade = { score: number; max: number; pct: number; released: boolean } | null;
type Row = {
    student: Student;
    enrollment_id: number;
    scores: Record<number, number | null>;
    assignment_grades: Record<number, AssignmentGrade>;
    midterm_grade: number | null;
    finals_grade: number | null;
    total_grade: number | null;
    final_grade: string | null;
    midterm_final_grade: string | null;
    finals_final_grade: string | null;
};
type GradingComponent = { id: number; name: string; weight_percentage: number; max_score: number; period: string | null; is_locked: boolean };
type GradingItem = { id: number; component_id: number; name: string; max_score: number; is_enabled: boolean; order: number };
type Assignment = { id: number; title: string; max_score: number; type: string };
type Section = {
    id: number; name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
    faculty: { name: string };
};

const props = defineProps<{
    section: Section;
    components: GradingComponent[];
    items: GradingItem[];
    assignments: Assignment[];
    midtermWeight: number;
    finalsWeight: number;
    generalWeight: number;
    rows: Row[];
    hasCustomScale: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Class Record', href: '#' },
        ],
    },
});

const pendingScores = ref<Record<number, Record<number, string>>>({});

function getDisplayScore(row: Row, item: GradingItem): string {
    const pending = pendingScores.value[row.student.id]?.[item.id];
    if (pending !== undefined) return pending;
    const s = row.scores[item.id];
    return s !== null && s !== undefined ? s.toString() : '';
}

function onInput(studentId: number, itemId: number, val: string) {
    if (!pendingScores.value[studentId]) pendingScores.value[studentId] = {};
    pendingScores.value[studentId][itemId] = val;
}

function saveScore(row: Row, item: GradingItem) {
    const rawVal = pendingScores.value[row.student.id]?.[item.id];
    if (rawVal === undefined) return;
    const score = rawVal === '' ? null : parseFloat(rawVal);
    delete pendingScores.value[row.student.id]?.[item.id];
    router.post('/item-scores/update', {
        student_id: row.student.id,
        section_id: props.section.id,
        item_id: item.id,
        score,
    }, { preserveScroll: true });
}

function gradeColor(val: number | null): string {
    if (val === null) return 'text-muted-foreground';
    if (val >= 75) return 'text-green-600 font-semibold';
    if (val >= 60) return 'text-orange-500 font-semibold';
    return 'text-red-600 font-semibold';
}

function transmutedColor(grade: string | null): string {
    if (!grade) return 'text-muted-foreground';
    return parseFloat(grade) <= 3.0 ? 'text-green-600 font-bold' : 'text-red-600 font-bold';
}

function pctColor(pct: number): string {
    if (pct >= 75) return 'text-green-600';
    if (pct >= 60) return 'text-orange-500';
    return 'text-red-500';
}

const weightOk = (w: number) => Math.abs(w - 100) < 0.01;
const hasMidtermComponents = computed(() => props.components.some(c => c.period === 'midterm'));
const hasFinalsComponents  = computed(() => props.components.some(c => c.period === 'finals'));
const hasGeneralComponents = computed(() => props.components.some(c => !c.period));
const isWeightComplete = computed(() => {
    if (hasMidtermComponents.value && !weightOk(props.midtermWeight)) return false;
    if (hasFinalsComponents.value  && !weightOk(props.finalsWeight))  return false;
    if (hasGeneralComponents.value && !weightOk(props.generalWeight)) return false;
    return props.components.length > 0;
});
const enabledItems = computed(() => props.items.filter(i => i.is_enabled !== false));

const itemsByComponent = computed(() => {
    const map: Record<number, GradingItem[]> = {};
    for (const c of props.components) map[c.id] = [];
    for (const item of enabledItems.value) {
        if (!map[item.component_id]) map[item.component_id] = [];
        map[item.component_id].push(item);
    }
    for (const key of Object.keys(map)) {
        map[Number(key)].sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
    }
    return map;
});

const componentsWithItems = computed(() =>
    props.components.filter(c => (itemsByComponent.value[c.id]?.length ?? 0) > 0)
);

const hasPeriods = computed(() =>
    props.components.some(c => c.period === 'midterm' || c.period === 'finals')
);

function print() { window.print(); }

function releaseAll() {
    if (confirm('Release all component grades to students?')) {
        router.post(`/sections/${props.section.id}/class-record/release`);
    }
}

const typeLabel: Record<string, string> = { essay: 'Essay', mcq: 'MCQ', code: 'Code' };
</script>

<template>
    <Head :title="`Class Record — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <!-- Header -->
        <div class="flex items-start justify-between gap-4 print:hidden">
            <div>
                <h1 class="text-2xl font-semibold">Class Record</h1>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} — {{ section.subject.name }} · {{ section.name }}
                    · {{ section.semester.name }} {{ section.semester.school_year }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/components`">
                        <Settings class="mr-1.5 h-4 w-4" />Components
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/transmutation`">
                        <BarChart2 class="mr-1.5 h-4 w-4" />Transmutation
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/items`">Items</Link>
                </Button>
                <Button variant="outline" size="sm" @click="releaseAll" v-if="rows.length > 0">
                    <Lock class="mr-1.5 h-4 w-4" />Release Grades
                </Button>
                <Button variant="outline" size="sm" @click="print">
                    <Download class="mr-1.5 h-4 w-4" />Print / Export
                </Button>
            </div>
        </div>

        <!-- Weight warning -->
        <div v-if="!isWeightComplete && components.length > 0"
             class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-2 text-sm text-orange-700 print:hidden">
            <span v-if="hasMidtermComponents && !weightOk(midtermWeight)">Midterm weights: {{ midtermWeight.toFixed(1) }}%. </span>
            <span v-if="hasFinalsComponents  && !weightOk(finalsWeight)">Finals weights: {{ finalsWeight.toFixed(1) }}%. </span>
            <span v-if="hasGeneralComponents && !weightOk(generalWeight)">General weights: {{ generalWeight.toFixed(1) }}%. </span>
            Each group must total 100% for grades to compute correctly.
            <Link :href="`/sections/${section.id}/components`" class="underline ml-1">Fix →</Link>
        </div>

        <!-- Period tip -->
        <div v-if="components.length > 0 && !hasPeriods"
             class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm text-blue-700 print:hidden">
            Tip: Tag each component as <strong>Midterm</strong> or <strong>Finals</strong> in
            <Link :href="`/sections/${section.id}/components`" class="underline">Components</Link>
            to auto-compute Midterm Grade, Finals Grade, and Total.
        </div>

        <div v-if="components.length === 0" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground print:hidden">
            <p class="text-sm">No grading components set up yet.</p>
            <Button variant="outline" size="sm" class="mt-3" as-child>
                <Link :href="`/sections/${section.id}/components`">Set Up Components</Link>
            </Button>
        </div>

        <div v-else-if="rows.length === 0" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
            <p class="text-sm">No students enrolled in this section.</p>
        </div>

        <!-- Spreadsheet -->
        <div v-else class="overflow-x-auto rounded-xl border">
            <!-- Print header -->
            <div class="hidden print:block p-4 text-center border-b">
                <h2 class="text-lg font-bold">CLASS RECORD</h2>
                <p class="text-sm">{{ section.subject.name }} ({{ section.subject.code }}) · {{ section.name }}</p>
                <p class="text-sm">{{ section.semester.name }} {{ section.semester.school_year }} · Faculty: {{ section.faculty.name }}</p>
            </div>

            <table class="w-full min-w-max text-sm">
                <thead class="border-b bg-muted/50">
                    <!-- Group header row -->
                    <tr>
                        <th class="sticky left-0 z-10 bg-muted/50 px-4 py-2 text-left font-medium text-muted-foreground">#</th>
                        <th class="sticky left-8 z-10 bg-muted/50 px-4 py-2 text-left font-medium text-muted-foreground whitespace-nowrap">Student</th>

                        <!-- Component groups -->
                        <th
                            v-for="comp in componentsWithItems"
                            :key="comp.id"
                            class="px-3 py-2 text-center font-medium text-muted-foreground whitespace-nowrap"
                            :colspan="itemsByComponent[comp.id].length"
                        >
                            <div class="flex items-center justify-center gap-1">
                                <span>{{ comp.name }}</span>
                                <Badge v-if="comp.period" variant="outline" class="text-[10px] capitalize px-1 py-0">
                                    {{ comp.period }}
                                </Badge>
                            </div>
                            <div class="text-xs font-normal opacity-70">{{ comp.weight_percentage }}%</div>
                        </th>

                        <!-- Assignment group -->
                        <th
                            v-if="assignments.length > 0"
                            :colspan="assignments.length"
                            class="px-3 py-2 text-center font-medium text-muted-foreground border-l whitespace-nowrap"
                        >
                            Assignments
                        </th>

                        <!-- Grade summary columns -->
                        <th v-if="hasPeriods" class="px-4 py-2 text-center font-medium text-muted-foreground border-l whitespace-nowrap bg-blue-50/50">
                            Midterm
                        </th>
                        <th v-if="hasPeriods" class="px-4 py-2 text-center font-medium text-muted-foreground whitespace-nowrap bg-blue-50/50">
                            Finals
                        </th>
                        <th class="px-4 py-2 text-center font-medium text-muted-foreground whitespace-nowrap"
                            :class="hasPeriods ? 'bg-blue-50/50' : ''">
                            Total %
                        </th>
                        <th class="px-4 py-2 text-center font-medium text-muted-foreground whitespace-nowrap bg-primary/5">
                            Final Grade
                        </th>
                    </tr>

                    <!-- Item sub-header row -->
                    <tr class="border-t bg-muted/30">
                        <th class="sticky left-0 z-10 bg-muted/30 px-4 py-1.5"></th>
                        <th class="sticky left-8 z-10 bg-muted/30 px-4 py-1.5 text-left text-xs font-medium text-muted-foreground">Items</th>
                        <template v-for="comp in componentsWithItems" :key="comp.id">
                            <th
                                v-for="item in itemsByComponent[comp.id]"
                                :key="item.id"
                                class="px-2 py-1.5 text-center text-xs font-medium text-muted-foreground whitespace-nowrap"
                            >
                                <div>{{ item.name }}</div>
                                <div class="text-[10px] font-normal opacity-70">/ {{ item.max_score }}</div>
                            </th>
                        </template>
                        <template v-if="assignments.length > 0">
                            <th
                                v-for="a in assignments"
                                :key="a.id"
                                class="px-2 py-1.5 text-center text-xs font-medium text-muted-foreground whitespace-nowrap border-l first:border-l"
                            >
                                <div class="max-w-[100px] truncate">{{ a.title }}</div>
                                <div class="text-[10px] font-normal opacity-70">{{ typeLabel[a.type] }} / {{ a.max_score }}</div>
                            </th>
                        </template>
                        <th v-if="hasPeriods" class="px-4 py-1.5 border-l bg-blue-50/30"></th>
                        <th v-if="hasPeriods" class="px-4 py-1.5 bg-blue-50/30"></th>
                        <th class="px-4 py-1.5" :class="hasPeriods ? 'bg-blue-50/30' : ''"></th>
                        <th class="px-4 py-1.5 bg-primary/5"></th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    <tr
                        v-for="(row, index) in rows"
                        :key="row.student.id"
                        class="hover:bg-muted/20 transition-colors"
                    >
                        <td class="sticky left-0 z-10 bg-card px-4 py-2 text-center text-muted-foreground text-xs">{{ index + 1 }}</td>
                        <td class="sticky left-8 z-10 bg-card px-4 py-2 whitespace-nowrap">
                            <p class="font-medium">{{ row.student.last_name }}, {{ row.student.first_name }}</p>
                            <p class="font-mono text-xs text-muted-foreground">{{ row.student.student_no }}</p>
                        </td>

                        <!-- Item score inputs -->
                        <template v-for="comp in componentsWithItems" :key="comp.id">
                            <td
                                v-for="item in itemsByComponent[comp.id]"
                                :key="item.id"
                                class="px-2 py-1.5 text-center"
                            >
                                <input
                                    type="number" min="0" :max="item.max_score" step="0.5"
                                    :value="getDisplayScore(row, item)"
                                    :disabled="comp.is_locked"
                                    class="w-16 rounded-md border border-input bg-transparent px-2 py-1 text-center text-sm focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-50 disabled:cursor-not-allowed print:border-0"
                                    placeholder="—"
                                    @input="onInput(row.student.id, item.id, ($event.target as HTMLInputElement).value)"
                                    @blur="saveScore(row, item)"
                                    @keydown.enter="saveScore(row, item); ($event.target as HTMLInputElement).blur()"
                                />
                            </td>
                        </template>

                        <!-- Assignment grade cells (read-only) -->
                        <template v-if="assignments.length > 0">
                            <td
                                v-for="a in assignments"
                                :key="a.id"
                                class="px-3 py-1.5 text-center border-l first:border-l"
                            >
                                <template v-if="row.assignment_grades[a.id]">
                                    <span :class="pctColor(row.assignment_grades[a.id]!.pct)" class="text-sm font-medium">
                                        {{ row.assignment_grades[a.id]!.pct }}%
                                    </span>
                                    <div class="text-[10px] text-muted-foreground">
                                        {{ row.assignment_grades[a.id]!.score }}/{{ row.assignment_grades[a.id]!.max }}
                                    </div>
                                </template>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                        </template>

                        <!-- Midterm Grade -->
                        <td v-if="hasPeriods" class="px-4 py-2 text-center border-l bg-blue-50/20" :class="gradeColor(row.midterm_grade)">
                            <template v-if="row.midterm_grade !== null">
                                <div class="font-semibold">{{ row.midterm_final_grade }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.midterm_grade.toFixed(2) }}%</div>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <!-- Finals Grade -->
                        <td v-if="hasPeriods" class="px-4 py-2 text-center bg-blue-50/20" :class="gradeColor(row.finals_grade)">
                            <template v-if="row.finals_grade !== null">
                                <div class="font-semibold">{{ row.finals_final_grade }}</div>
                                <div class="text-xs text-muted-foreground">{{ row.finals_grade.toFixed(2) }}%</div>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <!-- Total -->
                        <td class="px-4 py-2 text-center" :class="[gradeColor(row.total_grade), hasPeriods ? 'bg-blue-50/20' : '']">
                            {{ row.total_grade !== null ? row.total_grade.toFixed(2) + '%' : '—' }}
                        </td>
                        <!-- Final Grade (transmuted) -->
                        <td class="px-4 py-2 text-center bg-primary/5" :class="transmutedColor(row.final_grade)">
                            {{ row.final_grade ?? '—' }}
                        </td>
                    </tr>
                </tbody>

                <!-- Summary footer -->
                <tfoot class="border-t bg-muted/30">
                    <tr>
                        <td colspan="2" class="sticky left-0 bg-muted/30 px-4 py-2 text-xs font-medium text-muted-foreground">
                            Class Average
                        </td>
                        <template v-for="comp in componentsWithItems" :key="comp.id">
                            <td
                                v-for="item in itemsByComponent[comp.id]"
                                :key="item.id"
                                class="px-2 py-2 text-center text-xs text-muted-foreground"
                            >
                                {{
                                    (() => {
                                        const s = rows.map(r => r.scores[item.id]).filter(v => v != null) as number[];
                                        return s.length ? (s.reduce((a,b)=>a+b,0)/s.length).toFixed(1) : '—';
                                    })()
                                }}
                            </td>
                        </template>
                        <template v-if="assignments.length > 0">
                            <td v-for="a in assignments" :key="a.id" class="px-3 py-2 text-center text-xs text-muted-foreground border-l">
                                {{
                                    (() => {
                                        const pcts = rows.map(r => r.assignment_grades[a.id]?.pct).filter(v => v != null) as number[];
                                        return pcts.length ? (pcts.reduce((a,b)=>a+b,0)/pcts.length).toFixed(1)+'%' : '—';
                                    })()
                                }}
                            </td>
                        </template>
                        <td v-if="hasPeriods" class="px-4 py-2 text-center text-xs text-muted-foreground border-l">
                            {{ (() => { const v = rows.map(r=>r.midterm_grade).filter(v=>v!=null) as number[]; return v.length?(v.reduce((a,b)=>a+b,0)/v.length).toFixed(2)+'%':'—'; })() }}
                        </td>
                        <td v-if="hasPeriods" class="px-4 py-2 text-center text-xs text-muted-foreground">
                            {{ (() => { const v = rows.map(r=>r.finals_grade).filter(v=>v!=null) as number[]; return v.length?(v.reduce((a,b)=>a+b,0)/v.length).toFixed(2)+'%':'—'; })() }}
                        </td>
                        <td class="px-4 py-2 text-center text-xs text-muted-foreground">
                            {{ (() => { const v = rows.map(r=>r.total_grade).filter(v=>v!=null) as number[]; return v.length?(v.reduce((a,b)=>a+b,0)/v.length).toFixed(2)+'%':'—'; })() }}
                        </td>
                        <td class="px-4 py-2 text-center text-xs text-muted-foreground bg-primary/5">
                            {{ rows.filter(r => r.final_grade && parseFloat(r.final_grade) <= 3.0).length }} / {{ rows.length }} passing
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="text-xs text-muted-foreground print:hidden">
            Enter scores per item · Assignment grades are pulled automatically from submissions · Tag components as Midterm/Finals to split the grade calculation
        </p>
    </div>
</template>

<style scoped>
@media print {
    table { font-size: 11px; }
    input { border: 1px solid #ccc !important; }
}
</style>
