<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { GraduationCap } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Student = {
    id: number;
    student_no: string;
    first_name: string;
    last_name: string;
    email: string;
    course: string;
    year_level: number;
};

const props = defineProps<{ student?: Student }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Students', href: '/students' },
        ],
    },
});

const form = useForm({
    student_no: props.student?.student_no ?? '',
    first_name: props.student?.first_name ?? '',
    last_name: props.student?.last_name ?? '',
    email: props.student?.email ?? '',
    course: props.student?.course ?? '',
    year_level: props.student?.year_level?.toString() ?? '1',
});

function submit() {
    if (props.student) {
        form.put(`/students/${props.student.id}`);
    } else {
        form.post('/students');
    }
}
</script>

<template>
    <Head :title="student ? 'Edit Student' : 'Add Student'" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <GraduationCap class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">{{ student ? 'Edit Student' : 'Add Student' }}</h1>
                <p class="text-sm text-muted-foreground">{{ student ? 'Update student information' : 'Register a new student' }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="max-w-xl rounded-xl border bg-card p-6 shadow-sm space-y-5">
            <div class="grid gap-1.5">
                <Label for="student_no">Student Number</Label>
                <Input id="student_no" v-model="form.student_no" placeholder="e.g. 2023-00001" required />
                <InputError :message="form.errors.student_no" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-1.5">
                    <Label for="first_name">First Name</Label>
                    <Input id="first_name" v-model="form.first_name" placeholder="Juan" required />
                    <InputError :message="form.errors.first_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="last_name">Last Name</Label>
                    <Input id="last_name" v-model="form.last_name" placeholder="Dela Cruz" required />
                    <InputError :message="form.errors.last_name" />
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label for="email">Email Address</Label>
                <Input id="email" type="email" v-model="form.email" placeholder="juan@school.edu.ph" required />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-1.5">
                    <Label for="course">Course / Program</Label>
                    <Input id="course" v-model="form.course" placeholder="e.g. BSIT" required />
                    <InputError :message="form.errors.course" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Year Level</Label>
                    <Select v-model="form.year_level" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Year" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="1">1st Year</SelectItem>
                            <SelectItem value="2">2nd Year</SelectItem>
                            <SelectItem value="3">3rd Year</SelectItem>
                            <SelectItem value="4">4th Year</SelectItem>
                            <SelectItem value="5">5th Year</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.year_level" />
                </div>
            </div>

            <div class="flex gap-3 border-t pt-5">
                <Button type="submit" :disabled="form.processing">
                    {{ student ? 'Update Student' : 'Add Student' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link href="/students">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
