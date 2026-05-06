<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BarChart3, TrendingUp } from 'lucide-vue-next';
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
    subject: { code: string; name: string };
    semester: { name: string; school_year: string };
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
            { title: 'My Classes', href: '/my-sections' },
            { title: 'My Grades', href: '#' },
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

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Header -->
        <div class="flex items-start gap-3">
            <Button variant="ghost" size="sm" as-child class="-ml-2 mt-0.5">
                <Link :href="`/my-sections/${section.id}`">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <BarChart3 class="h-5 w-5 text-primary" />
                    <h1 class="text-xl font-semibold">My Grades</h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ section.subject.code }} · {{ section.name }} · {{ section.semester.name }} {{ section.semester.school_year }}
                </p>
            </div>
        </div>

        <!-- Final grade summary -->
        <div v-if="weightedTotal !== null" class="rounded-xl border bg-card p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Overall Score</p>
                    <div class="mt-1 flex items-end gap-2">
                        <span class="text-4xl font-bold" :class="gradeColor(weightedTotal)">{{ weightedTotal }}%</span>
                    </div>
                </div>
                <div v-if="transmutedGrade" class="text-right">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Final Grade</p>
                    <div class="mt-1 flex items-center justify-end gap-2">
                        <TrendingUp class="h-5 w-5 text-primary" />
                        <span class="text-4xl font-bold text-primary">{{ transmutedGrade }}</span>
                    </div>
                </div>
            </div>
            <!-- Progress bar -->
            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full transition-all"
                    :class="weightedTotal >= 75 ? 'bg-primary' : 'bg-destructive'"
                    :style="{ width: `${Math.min(weightedTotal, 100)}%` }"
                />
            </div>
        </div>

        <div v-else class="rounded-xl border border-dashed p-5 text-center text-sm text-muted-foreground">
            Your final grade will appear here once all components have released scores.
        </div>

        <!-- No components -->
        <div v-if="components.length === 0" class="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
            No grading components have been set up for this section yet.
        </div>

        <!-- Component breakdown -->
        <div v-else class="space-y-4">
            <div
                v-for="comp in components"
                :key="comp.id"
                class="rounded-xl border bg-card shadow-sm overflow-hidden"
            >
                <!-- Component header -->
                <div class="flex items-center justify-between border-b bg-muted/30 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold">{{ comp.name }}</span>
                        <Badge variant="outline" class="text-xs">{{ comp.weight }}%</Badge>
                    </div>
                    <div class="text-right text-sm">
                        <span v-if="comp.percentage !== null">
                            <span class="font-bold" :class="gradeColor(comp.percentage)">{{ comp.percentage }}%</span>
                            <span class="ml-1 text-xs text-muted-foreground">
                                (weighted: {{ comp.weighted?.toFixed(2) }})
                            </span>
                        </span>
                        <span v-else class="text-xs text-muted-foreground">No released scores</span>
                    </div>
                </div>

                <!-- Items -->
                <div class="divide-y">
                    <div
                        v-for="item in comp.items"
                        :key="item.id"
                        class="flex items-center justify-between px-4 py-2.5 text-sm"
                    >
                        <span class="text-muted-foreground">{{ item.name }}</span>
                        <div class="flex items-center gap-2">
                            <span v-if="item.is_released" class="font-medium">
                                {{ item.score }} / {{ item.max_score }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground">Not released</span>
                            <div
                                v-if="item.is_released && item.max_score > 0"
                                class="h-1.5 w-20 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{ width: `${Math.min((item.score! / item.max_score) * 100, 100)}%` }"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-if="comp.items.length === 0" class="px-4 py-3 text-xs text-muted-foreground">
                        No grading items added to this component.
                    </div>
                </div>

                <!-- Component score row -->
                <div v-if="comp.earned !== null" class="flex items-center justify-between border-t bg-muted/20 px-4 py-2 text-xs text-muted-foreground">
                    <span>Total Earned</span>
                    <span class="font-medium">{{ comp.earned }} / {{ comp.total }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
