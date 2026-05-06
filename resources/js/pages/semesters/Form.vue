<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

type Semester = {
    id: number;
    name: string;
    school_year: string;
    start_date: string;
    end_date: string;
};

const props = defineProps<{ semester?: Semester }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Semesters', href: '/admin/semesters' },
        ],
    },
});

const form = useForm({
    name: props.semester?.name ?? '',
    school_year: props.semester?.school_year ?? '',
    start_date: props.semester?.start_date ?? '',
    end_date: props.semester?.end_date ?? '',
});

function submit() {
    if (props.semester) {
        form.put(`/admin/semesters/${props.semester.id}`);
    } else {
        form.post('/admin/semesters');
    }
}
</script>

<template>
    <Head :title="semester ? 'Edit Semester' : 'New Semester'" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Page header -->
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <CalendarDays class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">{{ semester ? 'Edit Semester' : 'New Semester' }}</h1>
                <p class="text-sm text-muted-foreground">{{ semester ? 'Update semester details' : 'Create a new academic semester' }}</p>
            </div>
        </div>

        <!-- Form card -->
        <form @submit.prevent="submit" class="max-w-xl rounded-xl border bg-card p-6 shadow-sm space-y-5">
            <div class="grid gap-1.5">
                <Label for="name">Semester Name</Label>
                <Input id="name" v-model="form.name" placeholder="e.g. 1st Semester" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-1.5">
                <Label for="school_year">School Year</Label>
                <Input id="school_year" v-model="form.school_year" placeholder="e.g. 2025–2026" required />
                <InputError :message="form.errors.school_year" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-1.5">
                    <Label for="start_date">Start Date</Label>
                    <Input id="start_date" type="date" v-model="form.start_date" required />
                    <InputError :message="form.errors.start_date" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="end_date">End Date</Label>
                    <Input id="end_date" type="date" v-model="form.end_date" required />
                    <InputError :message="form.errors.end_date" />
                </div>
            </div>

            <div class="flex gap-3 border-t pt-5">
                <Button type="submit" :disabled="form.processing">
                    {{ semester ? 'Update Semester' : 'Create Semester' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link href="/admin/semesters">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
