<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, CheckCircle, Plus, Trash2, Pencil } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Semester = {
    id: number;
    name: string;
    school_year: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
};

defineProps<{ semesters: Semester[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Semesters', href: '/admin/semesters' },
        ],
    },
});

function setActive(id: number) {
    router.post(`/admin/semesters/${id}/set-active`);
}

function deleteSemester(id: number) {
    if (confirm('Delete this semester? This cannot be undone.')) {
        router.delete(`/admin/semesters/${id}`);
    }
}
</script>

<template>
    <Head title="Semesters" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Semesters</h1>
                <p class="text-sm text-muted-foreground">Manage academic semesters</p>
            </div>
            <Button as-child>
                <Link href="/admin/semesters/create">
                    <Plus class="mr-2 h-4 w-4" />
                    New Semester
                </Link>
            </Button>
        </div>

        <div v-if="semesters.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No semesters yet. Create your first one.
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card v-for="semester in semesters" :key="semester.id" class="relative">
                <CardHeader class="flex flex-row items-start justify-between space-y-0 pb-2">
                    <CardTitle class="text-base font-semibold">{{ semester.name }}</CardTitle>
                    <Badge :variant="semester.is_active ? 'default' : 'secondary'">
                        {{ semester.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p class="text-sm text-muted-foreground">{{ semester.school_year }}</p>
                    <div class="flex items-center gap-1 text-xs text-muted-foreground">
                        <CalendarDays class="h-3.5 w-3.5" />
                        {{ semester.start_date }} — {{ semester.end_date }}
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <Button
                            v-if="!semester.is_active"
                            variant="outline"
                            size="sm"
                            class="flex-1"
                            @click="setActive(semester.id)"
                        >
                            <CheckCircle class="mr-1.5 h-3.5 w-3.5" />
                            Set Active
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="`/admin/semesters/${semester.id}/edit`">
                                <Pencil class="h-3.5 w-3.5" />
                            </Link>
                        </Button>
                        <Button
                            v-if="!semester.is_active"
                            variant="outline"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            @click="deleteSemester(semester.id)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
