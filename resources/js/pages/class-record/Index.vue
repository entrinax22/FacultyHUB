<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Settings, BarChart2, Download, Lock } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Student = { id: number; student_no: string; first_name: string; last_name: string; course: string; year_level: number };
type Row = {
    student: Student;
    enrollment_id: number;
    scores: Record<number, number | null>;
    final_grade: number | null;
    transmuted: string | null;
};
type GradingComponent = { id: number; name: string; weight_percentage: number; max_score: number; is_locked: boolean };
type GradingItem = { id: number; component_id: number; name: string; max_score: number; is_enabled: boolean; order: number };
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
    totalWeight: number;
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

// Local reactive score map: pendingScores[studentId][itemId] = score string
const pendingScores = ref<Record<number, Record<number, string>>>({});

function pendingKey(studentId: number, itemId: number): string {
    return `${studentId}_${itemId}`;
}

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

    // Optimistically clear pending
    delete pendingScores.value[row.student.id]?.[item.id];

    router.post('/item-scores/update', {
        student_id: row.student.id,
        section_id: props.section.id,
        item_id: item.id,
        score,
    }, { preserveScroll: true });
}

function gradeColor(final: number | null): string {
    if (final === null) return '';
    if (final >= 75) return 'text-green-600 font-semibold';
    if (final >= 60) return 'text-orange-500 font-semibold';
    return 'text-red-600 font-semibold';
}

function transmutedColor(grade: string | null): string {
    if (!grade) return '';
    const val = parseFloat(grade);
    if (val <= 3.0) return 'text-green-600';
    return 'text-red-600';
}

const isWeightComplete = computed(() => Math.abs(props.totalWeight - 100) < 0.01);

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

const componentsWithItems = computed(() => props.components.filter(c => (itemsByComponent.value[c.id]?.length ?? 0) > 0));

function print() {
    window.print();
}

function releaseAll() {
    if (confirm('Release all component grades to students?')) {
        router.post(`/sections/${props.section.id}/class-record/release`);
    }
}
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
                    <Link :href="`/sections/${section.id}/items`">
                        Items
                    </Link>
                </Button>
                <Button variant="outline" size="sm" @click="releaseAll" v-if="rows.length > 0">
                    <Lock class="mr-1.5 h-4 w-4" />
                    Release Grades
                </Button>
                <Button variant="outline" size="sm" @click="print">
                    <Download class="mr-1.5 h-4 w-4" />
                    Print / Export
                </Button>
            </div>
        </div>

        <!-- Weight warning -->
        <div v-if="!isWeightComplete && components.length > 0" class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-2 text-sm text-orange-700 print:hidden">
            Grading components only add up to {{ totalWeight.toFixed(1) }}%. Final grades may be inaccurate.
            <Link :href="`/sections/${section.id}/components`" class="underline ml-1">Fix components →</Link>
        </div>

        <!-- No components yet -->
        <div v-if="components.length === 0" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground print:hidden">
            <p class="text-sm">No grading components set up yet.</p>
            <Button variant="outline" size="sm" class="mt-3" as-child>
                <Link :href="`/sections/${section.id}/components`">Set Up Components</Link>
            </Button>
        </div>

        <!-- No students enrolled -->
        <div v-else-if="rows.length === 0" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
            <p class="text-sm">No students enrolled in this section.</p>
        </div>

        <!-- No items yet -->
        <div v-else-if="componentsWithItems.length === 0" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground print:hidden">
            <p class="text-sm">No assessment items yet (e.g., Quiz 1, Activity 1).</p>
            <Button variant="outline" size="sm" class="mt-3" as-child>
                <Link :href="`/sections/${section.id}/items`">Add Items</Link>
            </Button>
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
                    <!-- Component name row -->
                    <tr>
                        <th class="sticky left-0 z-10 bg-muted/50 px-4 py-2 text-left font-medium text-muted-foreground whitespace-nowrap">#</th>
                        <th class="sticky left-8 z-10 bg-muted/50 px-4 py-2 text-left font-medium text-muted-foreground whitespace-nowrap">Student</th>
                        <th
                            v-for="comp in componentsWithItems"
                            :key="comp.id"
                            class="px-3 py-2 text-center font-medium text-muted-foreground whitespace-nowrap"
                            :colspan="itemsByComponent[comp.id].length"
                        >
                            <div>{{ comp.name }}</div>
                            <div class="text-xs font-normal opacity-70">{{ comp.weight_percentage }}%</div>
                        </th>
                        <th class="px-4 py-2 text-center font-medium text-muted-foreground whitespace-nowrap">Final %</th>
                        <th class="px-4 py-2 text-center font-medium text-muted-foreground whitespace-nowrap">Grade</th>
                    </tr>
                    <!-- Item row -->
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
                        <th class="px-4 py-1.5"></th>
                        <th class="px-4 py-1.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="(row, index) in rows"
                        :key="row.student.id"
                        class="hover:bg-muted/20 transition-colors"
                    >
                        <!-- Row number -->
                        <td class="sticky left-0 z-10 bg-card px-4 py-2 text-center text-muted-foreground text-xs">
                            {{ index + 1 }}
                        </td>
                        <!-- Student info -->
                        <td class="sticky left-8 z-10 bg-card px-4 py-2 whitespace-nowrap">
                            <p class="font-medium">{{ row.student.last_name }}, {{ row.student.first_name }}</p>
                            <p class="font-mono text-xs text-muted-foreground">{{ row.student.student_no }}</p>
                        </td>
                        <!-- Score cells -->
                        <template v-for="comp in componentsWithItems" :key="comp.id">
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
                                    :disabled="comp.is_locked"
                                    class="w-16 rounded-md border border-input bg-transparent px-2 py-1 text-center text-sm focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-50 disabled:cursor-not-allowed print:border-0"
                                    placeholder="—"
                                    @input="onInput(row.student.id, item.id, ($event.target as HTMLInputElement).value)"
                                    @blur="saveScore(row, item)"
                                    @keydown.enter="saveScore(row, item); ($event.target as HTMLInputElement).blur()"
                                />
                            </td>
                        </template>
                        <!-- Final grade -->
                        <td class="px-4 py-2 text-center" :class="gradeColor(row.final_grade)">
                            {{ row.final_grade !== null ? row.final_grade.toFixed(2) + '%' : '—' }}
                        </td>
                        <!-- Transmuted grade -->
                        <td class="px-4 py-2 text-center font-semibold" :class="transmutedColor(row.transmuted)">
                            {{ row.transmuted ?? '—' }}
                        </td>
                    </tr>
                </tbody>
                <!-- Summary row -->
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
                                        const scores = rows
                                            .map(r => r.scores[item.id])
                                            .filter(s => s !== null && s !== undefined) as number[];
                                        return scores.length
                                            ? (scores.reduce((a, b) => a + b, 0) / scores.length).toFixed(1)
                                            : '—';
                                    })()
                                }}
                            </td>
                        </template>
                        <td class="px-4 py-2 text-center text-xs text-muted-foreground">
                            {{
                                (() => {
                                    const finals = rows.map(r => r.final_grade).filter(f => f !== null) as number[];
                                    return finals.length
                                        ? (finals.reduce((a, b) => a + b, 0) / finals.length).toFixed(2) + '%'
                                        : '—';
                                })()
                            }}
                        </td>
                        <td class="px-4 py-2 text-center text-xs text-muted-foreground">
                            {{ rows.filter(r => r.transmuted && parseFloat(r.transmuted) <= 3.0).length }}
                            / {{ rows.length }} passing
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="text-xs text-muted-foreground print:hidden">
            Record scores per item (Quiz 1, Activity 1, etc.). Disabled items are excluded from computations. Locked components cannot be edited.
        </p>
    </div>
</template>

<style scoped>
@media print {
    table { font-size: 11px; }
    input { border: 1px solid #ccc !important; }
}
</style>
