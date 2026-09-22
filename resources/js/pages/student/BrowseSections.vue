<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

import {
    Search,
    BookOpen,
    CheckCircle,
    Users,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type SectionItem = {
    id: string;
    name: string;
    schedule: string | null;
    subject_code: string;
    subject_name: string;
    faculty_name: string;
    semester: string;
    enrollments_count: number;
    is_enrolled: boolean;
};

type ActiveSemester = {
    id: string;
    name: string;
    school_year: string;
};

const props = defineProps<{
    sections: SectionItem[];
    activeSemester: ActiveSemester | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Classes',
                href: '/my-sections',
            },
            {
                title: 'Browse Sections',
                href: '/my-sections/browse',
            },
        ],
    },
});

const search = ref('');
const enrolling = ref<string | null>(null);

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim();

    if (!q) {
        return props.sections;
    }

    return props.sections.filter(
        (section) =>
            section.subject_code.toLowerCase().includes(q) ||
            section.subject_name.toLowerCase().includes(q) ||
            section.name.toLowerCase().includes(q) ||
            section.faculty_name.toLowerCase().includes(q),
    );
});

function enroll(sectionId: string) {
    enrolling.value = sectionId;

    router.post(
        `/my-sections/${sectionId}/self-enroll`,
        {},
        {
            preserveScroll: true,

            onFinish: () => {
                enrolling.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Browse Sections" />

    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- Header -->
        <div class="flex min-w-0 items-center gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10"
            >
                <Search class="h-5 w-5 text-primary" />
            </div>

            <div class="min-w-0">
                <h1 class="text-xl font-semibold sm:text-2xl">
                    Browse Sections
                </h1>

                <p
                    v-if="activeSemester"
                    class="mt-1 truncate text-xs text-muted-foreground sm:text-sm"
                >
                    {{ activeSemester.name }}
                    {{ activeSemester.school_year }}
                </p>

                <p
                    v-else
                    class="mt-1 text-xs text-orange-500 sm:text-sm"
                >
                    No active semester
                </p>
            </div>
        </div>

        <!-- Search -->
        <div class="relative w-full sm:max-w-md">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
            />

            <Input
                v-model="search"
                placeholder="Search by subject, section, or faculty…"
                class="w-full pl-9"
            />
        </div>

        <!-- Empty State -->
        <div
            v-if="filtered.length === 0"
            class="flex min-h-48 items-center justify-center rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground sm:min-h-56"
        >
            <div>
                <p v-if="search">
                    No sections match "{{ search }}".
                </p>

                <p v-else>
                    No sections available for enrollment.
                </p>
            </div>
        </div>

        <!-- Section Grid -->
        <div
            v-else
            class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3"
        >
            <div
                v-for="section in filtered"
                :key="section.id"
                class="flex min-w-0 flex-col rounded-xl border bg-card p-4 shadow-sm transition-colors"
                :class="
                    section.is_enrolled
                        ? 'border-primary/30 bg-primary/5'
                        : 'hover:bg-muted/20'
                "
            >
                <!-- Card Header -->
                <div
                    class="flex min-w-0 items-start justify-between gap-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">
                            {{ section.subject_code }}
                        </p>

                        <p
                            class="mt-0.5 break-words text-sm text-muted-foreground"
                        >
                            {{ section.subject_name }}
                        </p>
                    </div>

                    <Badge
                        v-if="section.is_enrolled"
                        variant="default"
                        class="shrink-0 text-xs"
                    >
                        <CheckCircle
                            class="mr-1 h-3 w-3 shrink-0"
                        />

                        Enrolled
                    </Badge>
                </div>

                <!-- Card Details -->
                <div
                    class="mt-4 flex-1 space-y-2 text-xs text-muted-foreground"
                >
                    <!-- Section -->
                    <p
                        class="flex min-w-0 items-start gap-1.5"
                    >
                        <BookOpen
                            class="mt-0.5 h-3.5 w-3.5 shrink-0"
                        />

                        <span class="break-words">
                            {{ section.name }}
                        </span>
                    </p>

                    <!-- Schedule -->
                    <p
                        v-if="section.schedule"
                        class="break-words pl-5"
                    >
                        {{ section.schedule }}
                    </p>

                    <!-- Faculty -->
                    <p class="break-words pl-5">
                        {{ section.faculty_name }}
                    </p>

                    <!-- Students -->
                    <p class="flex items-center gap-1.5">
                        <Users
                            class="h-3.5 w-3.5 shrink-0"
                        />

                        <span>
                            {{ section.enrollments_count }}
                            enrolled
                        </span>
                    </p>
                </div>

                <!-- Action -->
                <div class="mt-4 border-t pt-4">
                    <Button
                        v-if="!section.is_enrolled"
                        class="w-full"
                        size="sm"
                        :disabled="enrolling === section.id"
                        @click="enroll(section.id)"
                    >
                        {{
                            enrolling === section.id
                                ? 'Enrolling…'
                                : 'Enroll in this Section'
                        }}
                    </Button>

                    <p
                        v-else
                        class="flex min-h-9 items-center justify-center text-center text-xs font-medium text-primary"
                    >
                        <CheckCircle
                            class="mr-1.5 h-3.5 w-3.5"
                        />

                        You are enrolled in this section
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
