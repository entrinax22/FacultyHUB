<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Trash2, ArrowLeft, RefreshCw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type ScaleRow = { id?: number; min_score: number; max_score: number; grade: string; description: string };
type Section = {
    id: number; name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};
type DefaultRow = { min: number; max: number; grade: string; description: string };

const props = defineProps<{
    section: Section;
    scale: ScaleRow[];
    defaultScale: DefaultRow[];
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

const rows = ref<ScaleRow[]>(
    props.scale.length > 0
        ? props.scale.map(r => ({ ...r }))
        : props.defaultScale.map(r => ({ min_score: r.min, max_score: r.max, grade: r.grade, description: r.description }))
);

function addRow() {
    rows.value.push({ min_score: 0, max_score: 0, grade: '', description: '' });
}

function removeRow(i: number) {
    rows.value.splice(i, 1);
}

function loadDefault() {
    rows.value = props.defaultScale.map(r => ({ min_score: r.min, max_score: r.max, grade: r.grade, description: r.description }));
}

function save() {
    router.post(`/sections/${props.section.id}/transmutation`, { rows: rows.value });
}

function applyDefault() {
    if (confirm('Apply the default Philippine grading scale? This will replace your current custom scale.')) {
        router.post(`/sections/${props.section.id}/transmutation/default`);
    }
}

function resetScale() {
    if (confirm('Remove custom scale and use default for this section?')) {
        router.delete(`/sections/${props.section.id}/transmutation`);
    }
}
</script>

<template>
    <Head :title="`Transmutation — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-2xl">
        <div class="flex items-start gap-3">
            <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                <Link :href="`/sections/${section.id}/class-record`"><ArrowLeft class="h-4 w-4" /></Link>
            </Button>
            <div>
                <h1 class="text-xl font-semibold">Transmutation Scale</h1>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} · {{ section.name }}
                </p>
            </div>
        </div>

        <div class="flex gap-2">
            <Button variant="outline" size="sm" @click="loadDefault">
                <RefreshCw class="mr-1.5 h-4 w-4" />
                Load Philippine Default
            </Button>
            <Button variant="outline" size="sm" @click="applyDefault">
                Apply Default to DB
            </Button>
            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="resetScale" v-if="scale.length > 0">
                Reset to Default
            </Button>
        </div>

        <!-- Scale editor -->
        <div class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50">
                    <tr>
                        <th class="px-3 py-2.5 text-center font-medium text-muted-foreground">Min %</th>
                        <th class="px-3 py-2.5 text-center font-medium text-muted-foreground">Max %</th>
                        <th class="px-3 py-2.5 text-center font-medium text-muted-foreground">Grade</th>
                        <th class="px-3 py-2.5 text-left font-medium text-muted-foreground">Description</th>
                        <th class="px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="(row, i) in rows" :key="i" class="hover:bg-muted/20">
                        <td class="px-2 py-1.5">
                            <Input type="number" v-model="row.min_score" min="0" max="100" step="0.5" class="w-20 text-center" />
                        </td>
                        <td class="px-2 py-1.5">
                            <Input type="number" v-model="row.max_score" min="0" max="100" step="0.5" class="w-20 text-center" />
                        </td>
                        <td class="px-2 py-1.5">
                            <Input v-model="row.grade" placeholder="1.00" class="w-20 text-center font-mono" />
                        </td>
                        <td class="px-2 py-1.5">
                            <Input v-model="row.description" placeholder="e.g. Excellent" class="w-36" />
                        </td>
                        <td class="px-2 py-1.5">
                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="removeRow(i)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex gap-3">
            <Button variant="outline" size="sm" @click="addRow">
                <Plus class="mr-1.5 h-4 w-4" />
                Add Row
            </Button>
            <Button @click="save">Save Scale</Button>
        </div>

        <div class="rounded-lg border bg-muted/20 p-3 text-xs text-muted-foreground">
            <strong>Default Philippine Grading Scale</strong> — 97–100 = 1.00 (Excellent) ... 75 = 3.00 (Passing) ... &lt;75 = 5.00 (Failed).
            Scores are matched from highest range downward. Custom scale applies to this section only.
        </div>
    </div>
</template>
