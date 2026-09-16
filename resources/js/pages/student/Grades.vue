<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart3,
    TrendingUp,
} from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type GradingItem = {
    id: number;
    name: string;
    max_score: number;
    score: number | null;
    is_released: boolean;
};

type Component = {
    id: number;
    name: string;
    weight: number;
    items: GradingItem[];
    earned: number | null;
    total: number | null;
    percentage: number | null;
    weighted: number | null;
};

type Section = {
    id: number;
    name: string;
    subject: {
        code: string;
        name: string;
    };
    semester: {
        name: string;
        school_year: string;
    };
};

defineProps<{
    section: Section;
    components: Component[];
    weightedTotal: number | null;
    transmutedGrade: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Classes',
                href: '/my-sections',
            },
            {
                title: 'My Grades',
                href: '#',
            },
        ],
    },
});

function gradeColor(pct: number | null): string {
    if (pct === null) return '';

    if (pct >= 90) return 'text-green-600';

    if (pct >= 75) return 'text-yellow-600';

    return 'text-red-600';
}
</script>

<template>
    <Head :title="`My Grades — ${section.subject.code}`" />

    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- ============================================================= -->
        <!-- HEADER -->
        <!-- ============================================================= -->

        <div class="flex min-w-0 items-start gap-2 sm:gap-3">
            <Button
                variant="ghost"
                size="sm"
                as-child
                class="-ml-2 mt-0.5 shrink-0"
            >
                <Link :href="`/my-sections/${section.id}`">
                    <ArrowLeft class="h-4 w-4" />
                    <span class="sr-only">Back</span>
                </Link>
            </Button>

            <div class="min-w-0 flex-1">
                <div class="flex min-w-0 items-center gap-2">
                    <BarChart3
                        class="h-5 w-5 shrink-0 text-primary"
                    />

                    <h1
                        class="truncate text-lg font-semibold sm:text-xl"
                    >
                        My Grades
                    </h1>
                </div>

                <p
                    class="mt-1 break-words text-xs text-muted-foreground sm:text-sm"
                >
                    <span class="font-medium text-foreground">
                        {{ section.subject.code }}
                    </span>
                    ·
                    {{ section.name }}
                    ·
                    {{ section.semester.name }}
                    {{ section.semester.school_year }}
                </p>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- FINAL GRADE SUMMARY -->
        <!-- ============================================================= -->

        <div
            v-if="weightedTotal !== null"
            class="rounded-xl border bg-card p-4 shadow-sm sm:p-5"
        >
            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <!-- Overall Score -->
                <div class="min-w-0">
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Overall Score
                    </p>

                    <div class="mt-1 flex items-end gap-2">
                        <span
                            class="text-3xl font-bold sm:text-4xl"
                            :class="gradeColor(weightedTotal)"
                        >
                            {{ weightedTotal }}%
                        </span>
                    </div>
                </div>

                <!-- Final Grade -->
                <div
                    v-if="transmutedGrade"
                    class="border-t pt-4 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0 sm:text-right"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Final Grade
                    </p>

                    <div
                        class="mt-1 flex items-center gap-2 sm:justify-end"
                    >
                        <TrendingUp
                            class="h-5 w-5 shrink-0 text-primary"
                        />

                        <span
                            class="text-3xl font-bold text-primary sm:text-4xl"
                        >
                            {{ transmutedGrade }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div
                class="mt-4 h-2 w-full overflow-hidden rounded-full bg-muted"
            >
                <div
                    class="h-full rounded-full transition-all"
                    :class="
                        weightedTotal >= 75
                            ? 'bg-primary'
                            : 'bg-destructive'
                    "
                    :style="{
                        width: `${Math.min(weightedTotal, 100)}%`,
                    }"
                />
            </div>
        </div>

        <!-- No final grade -->
        <div
            v-else
            class="rounded-xl border border-dashed p-5 text-center text-sm text-muted-foreground"
        >
            Your final grade will appear here once all components have
            released scores.
        </div>

        <!-- ============================================================= -->
        <!-- NO COMPONENTS -->
        <!-- ============================================================= -->

        <div
            v-if="components.length === 0"
            class="flex min-h-48 items-center justify-center rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground sm:min-h-56"
        >
            No grading components have been set up for this section yet.
        </div>

        <!-- ============================================================= -->
        <!-- COMPONENT BREAKDOWN -->
        <!-- ============================================================= -->

        <div v-else class="min-w-0 space-y-4">
            <div
                v-for="comp in components"
                :key="comp.id"
                class="min-w-0 overflow-hidden rounded-xl border bg-card shadow-sm"
            >
                <!-- ===================================================== -->
                <!-- COMPONENT HEADER -->
                <!-- ===================================================== -->

                <div
                    class="flex flex-col gap-2 border-b bg-muted/30 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <!-- Component name -->
                    <div
                        class="flex min-w-0 items-center gap-2 sm:gap-3"
                    >
                        <span
                            class="min-w-0 break-words font-semibold"
                        >
                            {{ comp.name }}
                        </span>

                        <Badge
                            variant="outline"
                            class="shrink-0 text-xs"
                        >
                            {{ comp.weight }}%
                        </Badge>
                    </div>

                    <!-- Component percentage -->
                    <div
                        class="text-left text-sm sm:shrink-0 sm:text-right"
                    >
                        <span
                            v-if="comp.percentage !== null"
                        >
                            <span
                                class="font-bold"
                                :class="
                                    gradeColor(comp.percentage)
                                "
                            >
                                {{ comp.percentage }}%
                            </span>

                            <span
                                v-if="comp.weighted !== null"
                                class="ml-1 text-xs text-muted-foreground"
                            >
                                (weighted:
                                {{ comp.weighted.toFixed(2) }})
                            </span>
                        </span>

                        <span
                            v-else
                            class="text-xs text-muted-foreground"
                        >
                            No released scores
                        </span>
                    </div>
                </div>

                <!-- ===================================================== -->
                <!-- GRADING ITEMS -->
                <!-- ===================================================== -->

                <div class="divide-y">
                    <div
                        v-for="item in comp.items"
                        :key="item.id"
                        class="flex min-w-0 flex-col gap-2 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                    >
                        <!-- Item name -->
                        <span
                            class="min-w-0 break-words text-muted-foreground"
                        >
                            {{ item.name }}
                        </span>

                        <!-- Score -->
                        <div
                            class="flex shrink-0 items-center justify-between gap-3 sm:justify-end"
                        >
                            <span
                                v-if="item.is_released"
                                class="font-medium"
                            >
                                {{ item.score }} /
                                {{ item.max_score }}
                            </span>

                            <span
                                v-else
                                class="text-xs text-muted-foreground"
                            >
                                Not released
                            </span>

                            <!-- Progress -->
                            <div
                                v-if="
                                    item.is_released &&
                                    item.max_score > 0
                                "
                                class="h-1.5 w-20 shrink-0 overflow-hidden rounded-full bg-muted sm:w-24"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{
                                        width: `${Math.min(
                                            ((item.score ?? 0) /
                                                item.max_score) *
                                                100,
                                            100,
                                        )}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- No items -->
                    <div
                        v-if="comp.items.length === 0"
                        class="px-4 py-3 text-xs text-muted-foreground"
                    >
                        No grading items added to this component.
                    </div>
                </div>

                <!-- ===================================================== -->
                <!-- COMPONENT TOTAL -->
                <!-- ===================================================== -->

                <div
                    v-if="comp.earned !== null"
                    class="flex flex-col gap-1 border-t bg-muted/20 px-4 py-2.5 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                >
                    <span>Total Earned</span>

                    <span class="font-medium text-foreground">
                        {{ comp.earned }} / {{ comp.total }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>