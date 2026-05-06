<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Layers } from 'lucide-vue-next';
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

type Semester = { id: number; name: string; school_year: string; is_active: boolean };
type Subject = { id: number; code: string; name: string };
type Section = {
    id: number;
    name: string;
    semester_id: number;
    subject_id: number;
    schedule: string | null;
    room: string | null;
};

const props = defineProps<{
    section?: Section;
    semesters: Semester[];
    subjects: Subject[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Sections', href: '/sections' },
        ],
    },
});

const form = useForm({
    name: props.section?.name ?? '',
    semester_id: props.section?.semester_id?.toString() ?? '',
    subject_id: props.section?.subject_id?.toString() ?? '',
    schedule: props.section?.schedule ?? '',
    room: props.section?.room ?? '',
});

function submit() {
    if (props.section) {
        form.put(`/sections/${props.section.id}`);
    } else {
        form.post('/sections');
    }
}
</script>

<template>
    <Head :title="section ? 'Edit Section' : 'New Section'" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <Layers class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">{{ section ? 'Edit Section' : 'New Section' }}</h1>
                <p class="text-sm text-muted-foreground">{{ section ? 'Update section details' : 'Create a new class section' }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="max-w-xl rounded-xl border bg-card p-6 shadow-sm space-y-5">
            <div class="grid gap-1.5">
                <Label for="name">Section Name</Label>
                <Input id="name" v-model="form.name" placeholder="e.g. BSIT 2-A" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-1.5">
                <Label>Semester</Label>
                <Select v-model="form.semester_id" required>
                    <SelectTrigger>
                        <SelectValue placeholder="Select a semester" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="sem in semesters"
                            :key="sem.id"
                            :value="sem.id.toString()"
                        >
                            {{ sem.name }} {{ sem.school_year }}
                            <span v-if="sem.is_active" class="ml-1 text-xs text-green-600">(Active)</span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.semester_id" />
            </div>

            <div class="grid gap-1.5">
                <Label>Subject</Label>
                <Select v-model="form.subject_id" required>
                    <SelectTrigger>
                        <SelectValue placeholder="Select a subject" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="sub in subjects"
                            :key="sub.id"
                            :value="sub.id.toString()"
                        >
                            {{ sub.code }} — {{ sub.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.subject_id" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-1.5">
                    <Label for="schedule">Schedule <span class="text-muted-foreground">(optional)</span></Label>
                    <Input id="schedule" v-model="form.schedule" placeholder="e.g. MWF 1:00–2:30 PM" />
                    <InputError :message="form.errors.schedule" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="room">Room <span class="text-muted-foreground">(optional)</span></Label>
                    <Input id="room" v-model="form.room" placeholder="e.g. Room 301" />
                    <InputError :message="form.errors.room" />
                </div>
            </div>

            <div class="flex gap-3 border-t pt-5">
                <Button type="submit" :disabled="form.processing">
                    {{ section ? 'Update Section' : 'Create Section' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link href="/sections">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
