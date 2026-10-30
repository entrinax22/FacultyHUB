<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, Sparkles } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

const props = defineProps<{
    user: boolean;
    canRegister: boolean;
    dashboardUrl: string;
    dashboardLabel: string;
}>();
</script>

<template>
    <section class="relative isolate overflow-hidden border-b border-border/40">
        <div
            class="landing-glow pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,hsl(74_74%_85%/.55),transparent_38%),linear-gradient(135deg,hsl(80_20%_98%),hsl(142_20%_94%))] dark:bg-[radial-gradient(circle_at_top_right,hsl(74_45%_28%/.35),transparent_38%),linear-gradient(135deg,hsl(142_20%_6%),hsl(142_20%_10%))]"
        />
        <div
            class="landing-float pointer-events-none absolute -top-32 -right-24 -z-10 size-96 rounded-full bg-primary/10 blur-3xl"
        />

        <div
            class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[1.1fr_.9fr] lg:px-8 lg:py-28"
        >
            <div>
                <div
                    class="landing-fade-up mb-6 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-background/70 px-3 py-2 text-xs font-semibold text-primary shadow-sm backdrop-blur"
                >
                    <Sparkles class="size-3.5" />
                    Built for connected learning
                </div>
                <h1
                    class="landing-fade-up max-w-3xl text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-6xl"
                    style="animation-delay: 100ms"
                >
                    One calm workspace for
                    <span class="brand-gradient-text"
                        >teaching and learning.</span
                    >
                </h1>
                <p
                    class="landing-fade-up mt-6 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg"
                    style="animation-delay: 200ms"
                >
                    FacultyHUB brings course materials, assessments, attendance,
                    grading, feedback, and academic progress together for
                    faculty and students.
                </p>
                <div
                    class="landing-fade-up mt-8 flex flex-col gap-3 sm:flex-row"
                    style="animation-delay: 300ms"
                >
                    <template v-if="!props.user">
                        <Button
                            size="lg"
                            class="brand-gradient brand-cta-text border-0 font-semibold shadow-lg"
                            as-child
                        >
                            <Link :href="login()"
                                >Start teaching <ArrowRight class="size-4"
                            /></Link>
                        </Button>
                        <Button
                            v-if="props.canRegister"
                            variant="outline"
                            size="lg"
                            as-child
                        >
                            <Link :href="register()">Create an account</Link>
                        </Button>
                    </template>
                    <Button
                        v-else
                        size="lg"
                        class="brand-gradient brand-cta-text border-0 font-semibold shadow-lg"
                        as-child
                    >
                        <Link :href="props.dashboardUrl"
                            >{{ props.dashboardLabel }}
                            <ArrowRight class="size-4"
                        /></Link>
                    </Button>
                </div>
                <div
                    class="landing-fade-up mt-8 flex flex-wrap gap-x-6 gap-y-3 text-xs text-muted-foreground"
                    style="animation-delay: 400ms"
                >
                    <span class="flex items-center gap-1.5"
                        ><CheckCircle2 class="size-3.5 text-primary" /> Faculty
                        and student workflows</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><CheckCircle2 class="size-3.5 text-primary" /> Secure
                        role-based access</span
                    >
                </div>
            </div>

            <div
                class="landing-fade-in relative hidden lg:block"
                style="animation-delay: 250ms"
                aria-label="FacultyHUB learning workspace demo preview"
            >
                <div
                    class="landing-float absolute -inset-4 rounded-[2rem] bg-primary/10 blur-2xl"
                    style="animation-delay: 1.5s"
                />
                <div
                    class="relative rounded-[2rem] border border-border/70 bg-card/95 p-5 shadow-2xl shadow-primary/10"
                >
                    <div
                        class="flex items-center justify-between border-b border-border/70 pb-4"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Faculty workspace
                                </p>
                                <span
                                    class="rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                                    >Demo Preview</span
                                >
                            </div>
                            <p class="mt-1 font-semibold">Today at a glance</p>
                        </div>
                        <span
                            class="rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground"
                            >Sample Data</span
                        >
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-muted/70 p-4">
                            <p class="text-xs text-muted-foreground">
                                Active sections
                            </p>
                            <p class="mt-2 text-2xl font-bold">06</p>
                        </div>
                        <div class="rounded-xl bg-muted/70 p-4">
                            <p class="text-xs text-muted-foreground">
                                Pending reviews
                            </p>
                            <p class="mt-2 text-2xl font-bold">18</p>
                        </div>
                    </div>
                    <div class="mt-3 rounded-xl border border-border/70 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold">Course activity</p>
                            <span class="text-xs text-primary"
                                >Illustrative</span
                            >
                        </div>
                        <div class="mt-5 flex h-24 items-end gap-2">
                            <span
                                v-for="height in [35, 52, 44, 70, 58, 82, 64]"
                                :key="height"
                                class="flex-1 rounded-t-md bg-primary/70"
                                :style="{ height: `${height}%` }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
