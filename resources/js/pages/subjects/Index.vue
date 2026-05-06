<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Subject = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    units: number;
    sections_count: number;
};

defineProps<{ subjects: Subject[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Subjects', href: '/subjects' },
        ],
    },
});

function deleteSubject(id: number) {
    if (confirm('Delete this subject?')) {
        router.delete(`/subjects/${id}`);
    }
}
</script>

<template>
    <Head title="Subjects" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Subjects</h1>
                <p class="text-sm text-muted-foreground">Manage course subjects</p>
            </div>
            <Button as-child>
                <Link href="/subjects/create">
                    <Plus class="mr-2 h-4 w-4" />
                    New Subject
                </Link>
            </Button>
        </div>

        <div v-if="subjects.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No subjects yet. Add your first subject.
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Code</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Name</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Units</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Sections</th>
                        <th class="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="subject in subjects" :key="subject.id" class="hover:bg-muted/30">
                        <td class="px-4 py-3">
                            <Badge variant="outline">{{ subject.code }}</Badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ subject.name }}</div>
                            <div v-if="subject.description" class="line-clamp-1 text-xs text-muted-foreground">
                                {{ subject.description }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">{{ subject.units }}</td>
                        <td class="px-4 py-3 text-center">{{ subject.sections_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="`/subjects/${subject.id}/edit`">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="deleteSubject(subject.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
