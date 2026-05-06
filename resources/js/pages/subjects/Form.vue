<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BookMarked } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

type Subject = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    units: number;
};

const props = defineProps<{ subject?: Subject }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Subjects', href: '/subjects' },
        ],
    },
});

const form = useForm({
    code: props.subject?.code ?? '',
    name: props.subject?.name ?? '',
    description: props.subject?.description ?? '',
    units: props.subject?.units ?? 3,
});

function submit() {
    if (props.subject) {
        form.put(`/subjects/${props.subject.id}`);
    } else {
        form.post('/subjects');
    }
}
</script>

<template>
    <Head :title="subject ? 'Edit Subject' : 'New Subject'" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                <BookMarked class="h-5 w-5 text-primary" />
            </div>
            <div>
                <h1 class="text-xl font-semibold">{{ subject ? 'Edit Subject' : 'New Subject' }}</h1>
                <p class="text-sm text-muted-foreground">{{ subject ? 'Update subject details' : 'Add a new subject to the system' }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="max-w-xl rounded-xl border bg-card p-6 shadow-sm space-y-5">
            <div class="grid gap-1.5">
                <Label for="code">Subject Code</Label>
                <Input id="code" v-model="form.code" placeholder="e.g. IT 224" required />
                <InputError :message="form.errors.code" />
            </div>

            <div class="grid gap-1.5">
                <Label for="name">Subject Name</Label>
                <Input id="name" v-model="form.name" placeholder="e.g. Web Development" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-1.5">
                <Label for="description">Description <span class="text-muted-foreground">(optional)</span></Label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    placeholder="Brief description of this subject"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-1.5 max-w-[140px]">
                <Label for="units">Units</Label>
                <Input id="units" type="number" v-model="form.units" min="0.5" max="10" step="0.5" required />
                <InputError :message="form.errors.units" />
            </div>

            <div class="flex gap-3 border-t pt-5">
                <Button type="submit" :disabled="form.processing">
                    {{ subject ? 'Update Subject' : 'Create Subject' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link href="/subjects">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
