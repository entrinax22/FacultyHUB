<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Student = {
    id: number;
    student_no: string;
    first_name: string;
    last_name: string;
    email: string;
    course: string;
    year_level: number;
    enrollments_count: number;
};

type PaginatedStudents = {
    data: Student[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

const props = defineProps<{ students: PaginatedStudents }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Students', href: '/students' },
        ],
    },
});

const searchQuery = ref('');

const filteredStudents = computed(() =>
    props.students.data.filter((s) => {
        const q = searchQuery.value.toLowerCase();
        return (
            s.student_no.toLowerCase().includes(q) ||
            s.first_name.toLowerCase().includes(q) ||
            s.last_name.toLowerCase().includes(q) ||
            s.email.toLowerCase().includes(q) ||
            s.course.toLowerCase().includes(q)
        );
    }),
);

function deleteStudent(id: number, name: string) {
    if (confirm(`Remove ${name} from the system? This cannot be undone.`)) {
        router.delete(`/students/${id}`);
    }
}
</script>

<template>
    <Head title="Students" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Students</h1>
                <p class="text-sm text-muted-foreground">{{ students.total }} total students</p>
            </div>
            <Button as-child>
                <Link href="/students/create">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Student
                </Link>
            </Button>
        </div>

        <!-- Search -->
        <div class="relative w-full max-w-sm">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchQuery" placeholder="Search by name, ID, email, course…" class="pl-9" />
        </div>

        <div v-if="students.data.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No students yet. Add your first student.
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Student No.</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Name</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Email</th>
                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">Course / Year</th>
                        <th class="px-4 py-3 text-center font-medium text-muted-foreground">Enrollments</th>
                        <th class="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="student in filteredStudents" :key="student.id" class="hover:bg-muted/30">
                        <td class="px-4 py-3 font-mono text-xs">{{ student.student_no }}</td>
                        <td class="px-4 py-3">
                            <Link
                                :href="`/students/${student.id}`"
                                class="font-medium hover:underline"
                            >
                                {{ student.last_name }}, {{ student.first_name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ student.email }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ student.course }} — Year {{ student.year_level }}
                        </td>
                        <td class="px-4 py-3 text-center text-muted-foreground">{{ student.enrollments_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="`/students/${student.id}/edit`">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="deleteStudent(student.id, `${student.first_name} ${student.last_name}`)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="students.last_page > 1" class="flex items-center justify-between text-sm">
            <p class="text-muted-foreground">Page {{ students.current_page }} of {{ students.last_page }}</p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!students.prev_page_url"
                    as-child
                >
                    <Link v-if="students.prev_page_url" :href="students.prev_page_url">Previous</Link>
                    <span v-else>Previous</span>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!students.next_page_url"
                    as-child
                >
                    <Link v-if="students.next_page_url" :href="students.next_page_url">Next</Link>
                    <span v-else>Next</span>
                </Button>
            </div>
        </div>
    </div>
</template>
