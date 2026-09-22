<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    RefreshCw,
} from 'lucide-vue-next';
import { ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    showApiError,
    showApiToast,
} from '@/lib/flashToast';

type Student = {
    id: string;
    first_name: string;
    last_name: string;
    student_no: string;
};

type Report = {
    id: string;
    similarity_score: number;
    flagged: boolean;
    explanation: string | null;
    student_a: Student;
    student_b: Student;
};

type Assignment = {
    id: string;
    title: string;
    section: {
        id: string;
        name: string;
        subject: {
            code: string;
        };
    };
};

const props = defineProps<{
    assignment: Assignment;
    reports: Report[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Assignments',
                href: '#',
            },
            {
                title: 'Plagiarism Report',
                href: '#',
            },
        ],
    },
});

const loading = ref(false);

async function rerun(): Promise<void> {
    if (loading.value) {
        return;
    }

    loading.value = true;

    try {
        const response = await axios.post(
            `/assignments/${props.assignment.id}/plagiarism/run`
        );

        showApiToast(response);
    } catch (error) {
        console.error(
            'Failed to run plagiarism check:',
            error
        );

        showApiError(error);
    } finally {
        loading.value = false;
    }
}

function scoreColor(score: number): string {
    if (score >= 70) {
        return 'text-red-600';
    }

    if (score >= 40) {
        return 'text-orange-500';
    }

    return 'text-green-600';
}
</script>

<template>
    <Head :title="`Plagiarism Report — ${assignment.title}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-3">
                <Button
                    variant="ghost"
                    size="sm"
                    as-child
                    class="-ml-2 mt-0.5"
                >
                    <Link
                        :href="`/assignments/${assignment.id}`"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>

                <div>
                    <h1 class="text-xl font-semibold">
                        Plagiarism Report
                    </h1>

                    <p class="text-sm text-muted-foreground">
                        {{ assignment.title }}
                        ·
                        {{ assignment.section.subject.code }}
                    </p>
                </div>
            </div>

            <Button
                variant="outline"
                size="sm"
                :disabled="loading"
                @click="rerun"
            >
                <RefreshCw
                    class="mr-1.5 h-4 w-4"
                    :class="{
                        'animate-spin': loading,
                    }"
                />

                {{
                    loading
                        ? 'Running...'
                        : 'Re-run Check'
                }}
            </Button>
        </div>

        <!-- Legend -->
        <div
            class="flex flex-wrap gap-4 text-xs text-muted-foreground"
        >
            <span class="flex items-center gap-1.5">
                <span
                    class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"
                />
                ≥70% Flagged
            </span>

            <span class="flex items-center gap-1.5">
                <span
                    class="inline-block h-2.5 w-2.5 rounded-full bg-orange-400"
                />
                40–69% Review
            </span>

            <span class="flex items-center gap-1.5">
                <span
                    class="inline-block h-2.5 w-2.5 rounded-full bg-green-500"
                />
                &lt;40% OK
            </span>
        </div>

        <!-- Empty State -->
        <div
            v-if="reports.length === 0"
            class="rounded-xl border border-dashed p-12 text-center text-muted-foreground"
        >
            No plagiarism data yet. Run the check from the
            assignment page.
        </div>

        <!-- Reports -->
        <div
            v-else
            class="space-y-3"
        >
            <!-- Flagged Summary -->
            <div
                v-if="reports.some((report) => report.flagged)"
                class="flex items-center gap-2 text-sm font-semibold text-red-600"
            >
                <AlertTriangle class="h-4 w-4" />

                {{
                    reports.filter(
                        (report) => report.flagged
                    ).length
                }}

                flagged pair{{
                    reports.filter(
                        (report) => report.flagged
                    ).length !== 1
                        ? 's'
                        : ''
                }}

                above 70% similarity
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Student A
                            </th>

                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Student B
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-muted-foreground"
                            >
                                Similarity
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium text-muted-foreground"
                            >
                                Flag
                            </th>

                            <th
                                class="px-4 py-3 text-left font-medium text-muted-foreground"
                            >
                                Explanation
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        <tr
                            v-for="report in reports"
                            :key="report.id"
                            class="hover:bg-muted/30"
                            :class="
                                report.flagged
                                    ? 'bg-red-50/40'
                                    : ''
                            "
                        >
                            <!-- Student A -->
                            <td class="px-4 py-3">
                                <p class="font-medium">
                                    {{
                                        report.student_a
                                            .last_name
                                    }},
                                    {{
                                        report.student_a
                                            .first_name
                                    }}
                                </p>

                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{
                                        report.student_a
                                            .student_no
                                    }}
                                </p>
                            </td>

                            <!-- Student B -->
                            <td class="px-4 py-3">
                                <p class="font-medium">
                                    {{
                                        report.student_b
                                            .last_name
                                    }},
                                    {{
                                        report.student_b
                                            .first_name
                                    }}
                                </p>

                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{
                                        report.student_b
                                            .student_no
                                    }}
                                </p>
                            </td>

                            <!-- Similarity -->
                            <td
                                class="px-4 py-3 text-center"
                            >
                                <span
                                    class="text-lg font-bold"
                                    :class="
                                        scoreColor(
                                            report.similarity_score
                                        )
                                    "
                                >
                                    {{
                                        report.similarity_score.toFixed(
                                            1
                                        )
                                    }}%
                                </span>
                            </td>

                            <!-- Flag -->
                            <td
                                class="px-4 py-3 text-center"
                            >
                                <Badge
                                    v-if="report.flagged"
                                    variant="destructive"
                                >
                                    Flagged
                                </Badge>

                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    —
                                </span>
                            </td>

                            <!-- Explanation -->
                            <td
                                class="max-w-xs px-4 py-3 text-xs text-muted-foreground"
                            >
                                {{
                                    report.explanation ||
                                    '—'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>