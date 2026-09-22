<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { BookMarked } from 'lucide-vue-next';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

type Subject = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    units: number;
};

const props = defineProps<{
    subject?: Subject;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Subjects', href: '/subjects' },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

const form = ref({
    code: props.subject?.code ?? '',
    name: props.subject?.name ?? '',
    description: props.subject?.description ?? '',
    units: props.subject?.units ?? 3,
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const processing = ref(false);

const errors = ref<{
    code?: string;
    name?: string;
    description?: string;
    units?: string;
}>({});

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

async function submit() {
    processing.value = true;
    errors.value = {};

    try {
        let response;

        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */

        if (props.subject) {
            response = await axios.put(
                `/subjects/update/${props.subject.id}`,
                form.value
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create
        |--------------------------------------------------------------------------
        */

        else {
            response = await axios.post(
                '/subjects/store',
                form.value
            );
        }

        /*
        |--------------------------------------------------------------------------
        | API Response
        |--------------------------------------------------------------------------
        */

        showApiToast(response);

        if (response.data.success) {
            window.location.href = '/subjects';
        }

    } catch (error: any) {

        /*
        |--------------------------------------------------------------------------
        | Validation Error
        |--------------------------------------------------------------------------
        */

        if (error.response?.status === 422) {
            const validationErrors =
                error.response.data.errors ?? {};

            errors.value = {
                code: validationErrors.code?.[0],
                name: validationErrors.name?.[0],
                description: validationErrors.description?.[0],
                units: validationErrors.units?.[0],
            };

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Other API Error
        |--------------------------------------------------------------------------
        */

        console.error('SUBJECT FORM ERROR:', error);

        showApiError(error);

    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Head :title="subject ? 'Edit Subject' : 'New Subject'" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10"
            >
                <BookMarked class="h-5 w-5 text-primary" />
            </div>

            <div>
                <h1 class="text-xl font-semibold">
                    {{ subject ? 'Edit Subject' : 'New Subject' }}
                </h1>

                <p class="text-sm text-muted-foreground">
                    {{
                        subject
                            ? 'Update subject details'
                            : 'Add a new subject to the system'
                    }}
                </p>
            </div>
        </div>

        <!-- Form -->
        <form
            @submit.prevent="submit"
            class="max-w-xl space-y-5 rounded-xl border bg-card p-6 shadow-sm"
        >

            <!-- Subject Code -->
            <div class="grid gap-1.5">
                <Label for="code">
                    Subject Code
                </Label>

                <Input
                    id="code"
                    v-model="form.code"
                    placeholder="e.g. IT 224"
                    required
                />

                <InputError :message="errors.code" />
            </div>

            <!-- Subject Name -->
            <div class="grid gap-1.5">
                <Label for="name">
                    Subject Name
                </Label>

                <Input
                    id="name"
                    v-model="form.name"
                    placeholder="e.g. Web Development"
                    required
                />

                <InputError :message="errors.name" />
            </div>

            <!-- Description -->
            <div class="grid gap-1.5">
                <Label for="description">
                    Description
                    <span class="text-muted-foreground">
                        (optional)
                    </span>
                </Label>

                <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    placeholder="Brief description of this subject"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                ></textarea>

                <InputError :message="errors.description" />
            </div>

            <!-- Units -->
            <div class="grid max-w-[140px] gap-1.5">
                <Label for="units">
                    Units
                </Label>

                <Input
                    id="units"
                    type="number"
                    v-model.number="form.units"
                    min="0.5"
                    max="10"
                    step="0.5"
                    required
                />

                <InputError :message="errors.units" />
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 border-t pt-5">

                <Button
                    type="submit"
                    :disabled="processing"
                >
                    {{
                        processing
                            ? 'Saving...'
                            : subject
                                ? 'Update Subject'
                                : 'Create Subject'
                    }}
                </Button>

                <Button
                    variant="outline"
                    as-child
                >
                    <Link href="/subjects">
                        Cancel
                    </Link>
                </Button>

            </div>
        </form>
    </div>
</template>