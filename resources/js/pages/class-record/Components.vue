<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Pencil, Trash2, Lock, Unlock, AlertTriangle, Check } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

type Component = {
    id: number; name: string; weight_percentage: number; max_score: number; order: number; is_locked: boolean;
};
type Section = {
    id: number; name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};

const props = defineProps<{
    section: Section;
    components: Component[];
    totalWeight: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Grading Components', href: '#' },
        ],
    },
});

const addForm = useForm({ name: '', weight_percentage: '', max_score: '100' });
const editingId = ref<number | null>(null);
const editForm = useForm({ name: '', weight_percentage: '', max_score: '100' });

function startEdit(comp: Component) {
    editingId.value = comp.id;
    editForm.name = comp.name;
    editForm.weight_percentage = comp.weight_percentage.toString();
    editForm.max_score = String(comp.max_score ?? 100);
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function saveEdit(compId: number) {
    editForm.put(`/components/${compId}`, {
        onSuccess: () => { editingId.value = null; },
    });
}

function addComponent() {
    addForm.post(`/sections/${props.section.id}/components`, {
        onSuccess: () => addForm.reset(),
    });
}

function deleteComponent(id: number, name: string) {
    if (confirm(`Remove "${name}"? All grades for this component will be deleted.`)) {
        router.delete(`/components/${id}`);
    }
}

function toggleLock(id: number) {
    router.post(`/components/${id}/toggle-lock`);
}

const remaining = (100 - props.totalWeight).toFixed(2);
const isComplete = Math.abs(props.totalWeight - 100) < 0.01;
</script>

<template>
    <Head :title="`Grading Components — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-2xl">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Grading Components</h1>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} · {{ section.name }} · {{ section.semester.name }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/items`">Items</Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/sections/${section.id}/class-record`">View Class Record</Link>
                </Button>
            </div>
        </div>

        <!-- Weight progress -->
        <div class="rounded-xl border p-4 space-y-2">
            <div class="flex justify-between text-sm">
                <span class="font-medium">Total Weight</span>
                <span :class="isComplete ? 'text-green-600 font-semibold' : 'text-orange-500'">
                    {{ totalWeight.toFixed(2) }}% / 100%
                </span>
            </div>
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full transition-all"
                    :class="isComplete ? 'bg-green-500' : totalWeight > 100 ? 'bg-red-500' : 'bg-primary'"
                    :style="{ width: `${Math.min(totalWeight, 100)}%` }"
                />
            </div>
            <p v-if="!isComplete && totalWeight < 100" class="flex items-center gap-1.5 text-xs text-orange-500">
                <AlertTriangle class="h-3.5 w-3.5" />
                {{ remaining }}% remaining — final grades won't compute until total = 100%
            </p>
            <p v-else-if="isComplete" class="flex items-center gap-1.5 text-xs text-green-600">
                <Check class="h-3.5 w-3.5" />
                Weights are complete. Final grades will be computed correctly.
            </p>
        </div>

        <!-- Components list -->
        <div class="space-y-2">
            <div v-if="components.length === 0" class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                No components yet. Add your first grading component below.
            </div>

            <div
                v-for="comp in components"
                :key="comp.id"
                class="rounded-xl border p-4"
            >
                <div v-if="editingId === comp.id" class="flex items-end gap-3">
                    <div class="grid flex-1 gap-1.5">
                        <Label class="text-xs">Name</Label>
                        <Input v-model="editForm.name" placeholder="e.g. Quizzes" />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <div class="grid w-28 gap-1.5">
                        <Label class="text-xs">Weight %</Label>
                        <Input type="number" v-model="editForm.weight_percentage" min="0.01" max="100" step="0.01" />
                        <InputError :message="editForm.errors.weight_percentage" />
                    </div>
                    <div class="grid w-28 gap-1.5">
                        <Label class="text-xs">Max Score</Label>
                        <Input type="number" v-model="editForm.max_score" min="1" step="1" />
                        <InputError :message="editForm.errors.max_score" />
                    </div>
                    <div class="flex gap-1.5">
                        <Button size="sm" :disabled="editForm.processing" @click="saveEdit(comp.id)">Save</Button>
                        <Button size="sm" variant="ghost" @click="cancelEdit">Cancel</Button>
                    </div>
                </div>

                <div v-else class="flex items-center gap-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-medium">{{ comp.name }}</p>
                            <Badge v-if="comp.is_locked" variant="secondary" class="text-xs">Locked</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ comp.weight_percentage }}% weight Â· scores out of {{ comp.max_score }}
                        </p>
                    </div>
                    <div class="h-2 w-24 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-primary" :style="{ width: `${comp.weight_percentage}%` }" />
                    </div>
                    <div class="flex gap-1">
                        <Button variant="ghost" size="sm" :title="comp.is_locked ? 'Unlock' : 'Lock'" @click="toggleLock(comp.id)">
                            <Lock v-if="!comp.is_locked" class="h-4 w-4" />
                            <Unlock v-else class="h-4 w-4" />
                        </Button>
                        <Button v-if="!comp.is_locked" variant="ghost" size="sm" @click="startEdit(comp)">
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button v-if="!comp.is_locked" variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="deleteComponent(comp.id, comp.name)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add new component -->
        <div class="rounded-xl border p-4 space-y-3">
            <p class="font-medium text-sm">Add Component</p>
            <div class="flex items-end gap-3">
                <div class="grid flex-1 gap-1.5">
                    <Label for="name" class="text-xs">Component Name</Label>
                    <Input id="name" v-model="addForm.name" placeholder="e.g. Quizzes, Assignments, Midterm Exam" />
                    <InputError :message="addForm.errors.name" />
                </div>
                <div class="grid w-32 gap-1.5">
                    <Label for="weight" class="text-xs">Weight %</Label>
                    <Input id="weight" type="number" v-model="addForm.weight_percentage" min="0.01" max="100" step="0.01" placeholder="30" />
                    <InputError :message="addForm.errors.weight_percentage" />
                </div>
                <div class="grid w-28 gap-1.5">
                    <Label for="max" class="text-xs">Max</Label>
                    <Input id="max" type="number" v-model="addForm.max_score" min="1" step="1" placeholder="100" />
                    <InputError :message="addForm.errors.max_score" />
                </div>
                <Button :disabled="addForm.processing" @click="addComponent">
                    <Plus class="mr-1.5 h-4 w-4" />
                    Add
                </Button>
            </div>

            <!-- Quick presets -->
            <div class="flex flex-wrap gap-2 pt-1">
                <p class="w-full text-xs text-muted-foreground">Quick presets:</p>
                <button
                    v-for="preset in [
                        { name: 'Quizzes', w: 30 }, { name: 'Assignments', w: 30 },
                        { name: 'Attendance', w: 10 }, { name: 'Midterm Exam', w: 15 },
                        { name: 'Final Exam', w: 15 },
                    ]"
                    :key="preset.name"
                    type="button"
                    class="rounded-md border px-2 py-1 text-xs hover:bg-muted transition-colors"
                    @click="addForm.name = preset.name; addForm.weight_percentage = preset.w.toString()"
                >
                    {{ preset.name }} ({{ preset.w }}%)
                </button>
            </div>
        </div>
    </div>
</template>
