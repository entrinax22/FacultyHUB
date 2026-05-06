<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Eye, EyeOff, Pencil, Trash2, FileText, Clock, CheckCircle2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Assignment = {
    id: number;
    title: string;
    type: 'essay' | 'mcq' | 'code';
    due_date: string | null;
    max_score: number;
    is_published: boolean;
    submissions_count: number;
};

type Section = {
    id: number;
    name: string;
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
};

defineProps<{ section: Section; assignments: Assignment[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sections', href: '/sections' },
            { title: 'Assignments', href: '#' },
        ],
    },
});

const typeLabel: Record<string, string> = { essay: 'Essay', mcq: 'Multiple Choice', code: 'Code' };
const typeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    essay: 'default',
    mcq: 'secondary',
    code: 'outline',
};

function togglePublish(id: number) {
    router.post(`/assignments/${id}/toggle-publish`, {}, { preserveScroll: true });
}

function deleteAssignment(id: number, title: string) {
    if (confirm(`Delete "${title}"? All submissions will be removed.`)) {
        router.delete(`/assignments/${id}`);
    }
}
</script>

<template>
    <Head :title="`Assignments — ${section.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Assignments</h1>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} · {{ section.name }} · {{ section.semester.name }} {{ section.semester.school_year }}
                </p>
            </div>
            <Button as-child>
                <Link :href="`/sections/${section.id}/assignments/create`">
                    <Plus class="mr-2 h-4 w-4" />
                    New Assignment
                </Link>
            </Button>
        </div>

        <div v-if="assignments.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No assignments yet. Create your first assignment.
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Title</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Due Date</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Max Score</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Submissions</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="a in assignments" :key="a.id" class="hover:bg-muted/30">
                        <td class="px-4 py-3">
                            <Link :href="`/assignments/${a.id}`" class="font-medium hover:underline">{{ a.title }}</Link>
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="typeVariant[a.type]">{{ typeLabel[a.type] }}</Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <span v-if="a.due_date" class="flex items-center gap-1">
                                <Clock class="h-3.5 w-3.5" />
                                {{ new Date(a.due_date).toLocaleDateString() }}
                            </span>
                            <span v-else class="text-xs">No deadline</span>
                        </td>
                        <td class="px-4 py-3 text-center">{{ a.max_score }}</td>
                        <td class="px-4 py-3 text-center">{{ a.submissions_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <Badge :variant="a.is_published ? 'default' : 'secondary'">
                                {{ a.is_published ? 'Published' : 'Draft' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="sm" :title="a.is_published ? 'Unpublish' : 'Publish'" @click="togglePublish(a.id)">
                                    <Eye v-if="!a.is_published" class="h-4 w-4" />
                                    <EyeOff v-else class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="`/assignments/${a.id}/edit`"><Pencil class="h-4 w-4" /></Link>
                                </Button>
                                <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="deleteAssignment(a.id, a.title)">
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
