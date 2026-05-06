<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, Pencil, Trash2, Power, ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

type Section = {
    id: number;
    name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};
type Component = { id: number; name: string; weight_percentage: number; is_locked: boolean };
type Item = {
    id: number;
    section_id: number;
    component_id: number;
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

const addForm = useForm({ component_id: '', name: '', max_score: '100' });
const editingId = ref<number | null>(null);
const editForm = useForm({ name: '', max_score: '100' });

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

function addItem() {
    addForm.post(`/sections/${props.section.id}/items`, { onSuccess: () => addForm.reset('name') });
}

function startEdit(item: Item) {
    editingId.value = item.id;
    editForm.name = item.name;
    editForm.max_score = String(item.max_score ?? 100);
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function saveEdit(itemId: number) {
    editForm.put(`/items/${itemId}`, { onSuccess: () => (editingId.value = null) });
}

function toggle(item: Item) {
    router.post(`/items/${item.id}/toggle`);
}

function remove(item: Item) {
    if (confirm(`Remove "${item.name}"? Scores recorded for it will also be deleted.`)) {
        router.delete(`/items/${item.id}`);
    }
}
</script>

<template>
    <Head :title="`Items — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-3xl">
        <div class="flex items-start gap-3">
            <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                <Link :href="`/sections/${section.id}/class-record`"><ArrowLeft class="h-4 w-4" /></Link>
            </Button>
            <div class="flex-1">
                <h1 class="text-2xl font-semibold">Assessment Items</h1>
                <p class="text-sm text-muted-foreground">
                    Add as many quizzes/activities as you want per component (e.g., Quiz 1, Quiz 2). Disable items to exclude them from computations.
                </p>
            </div>
        </div>

        <!-- Add item -->
        <div class="rounded-xl border p-4 space-y-3">
            <p class="font-medium text-sm">Add Item</p>
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label class="text-xs">Component</Label>
                    <select
                        v-model="addForm.component_id"
                        class="h-10 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="" disabled>Select component…</option>
                        <option v-for="c in components" :key="c.id" :value="c.id">{{ c.name }} ({{ c.weight_percentage }}%)</option>
                    </select>
                    <InputError :message="addForm.errors.component_id" />
                </div>

                <div class="grid gap-1.5">
                    <Label class="text-xs">Name</Label>
                    <Input v-model="addForm.name" placeholder="e.g. Quiz 1" />
                    <InputError :message="addForm.errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label class="text-xs">Max Score</Label>
                    <Input type="number" v-model="addForm.max_score" min="1" step="1" placeholder="20" />
                    <InputError :message="addForm.errors.max_score" />
                </div>
            </div>

            <Button :disabled="addForm.processing || !addForm.component_id" @click="addItem">
                <Plus class="mr-1.5 h-4 w-4" />
                Add Item
            </Button>
        </div>

        <!-- List -->
        <div class="space-y-3">
            <div
                v-for="c in components"
                :key="c.id"
                class="rounded-xl border overflow-hidden"
            >
                <div class="flex items-center justify-between border-b bg-muted/30 px-4 py-3">
                    <div>
                        <p class="font-medium">{{ c.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ c.weight_percentage }}% weight</p>
                    </div>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/sections/${section.id}/class-record`">Open Class Record</Link>
                    </Button>
                </div>

                <div v-if="itemsByComponent[c.id].length === 0" class="p-4 text-sm text-muted-foreground">
                    No items yet.
                </div>

                <div v-else class="divide-y">
                    <div
                        v-for="item in itemsByComponent[c.id]"
                        :key="item.id"
                        class="p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex-1">
                            <div v-if="editingId === item.id" class="grid gap-2 sm:grid-cols-2">
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Name</Label>
                                    <Input v-model="editForm.name" />
                                    <InputError :message="editForm.errors.name" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Max</Label>
                                    <Input type="number" v-model="editForm.max_score" min="1" step="1" />
                                    <InputError :message="editForm.errors.max_score" />
                                </div>
                                <div class="flex gap-2">
                                    <Button size="sm" :disabled="editForm.processing" @click="saveEdit(item.id)">Save</Button>
                                    <Button size="sm" variant="ghost" @click="cancelEdit">Cancel</Button>
                                </div>
                            </div>

                            <div v-else>
                                <p class="font-medium">
                                    {{ item.name }}
                                    <span class="ml-2 text-xs text-muted-foreground">/ {{ item.max_score }}</span>
                                    <span
                                        class="ml-2 rounded-md border px-2 py-0.5 text-[11px]"
                                        :class="item.is_enabled ? 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300' : 'bg-muted text-muted-foreground'"
                                    >
                                        {{ item.is_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div v-if="editingId !== item.id" class="flex gap-1.5">
                            <Button variant="ghost" size="sm" :title="item.is_enabled ? 'Disable' : 'Enable'" @click="toggle(item)">
                                <Power class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="sm" @click="startEdit(item)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="remove(item)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

