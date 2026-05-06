<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, ClipboardList, Users, Bot, BarChart2, CalendarCheck, ArrowRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

withDefaults(defineProps<{ canRegister: boolean }>(), { canRegister: true });

const features = [
    { icon: BookOpen,      title: 'Module Management',    desc: 'Upload PDFs and files. Students track reading progress with a visual completion bar.', color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' },
    { icon: ClipboardList, title: 'Assignments & Quizzes', desc: 'Create essays, MCQs, and code problems. MCQs are auto-graded instantly on submission.', color: 'bg-lime-50 text-lime-700 dark:bg-lime-950 dark:text-lime-300' },
    { icon: Bot,           title: 'AI-Powered Grading',   desc: 'Claude AI grades essays and code. Faculty review, approve, or override before releasing.', color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' },
    { icon: BarChart2,     title: 'Plagiarism Detection',  desc: 'Batch-compare essay submissions. Flagged pairs above 70% similarity surface instantly.', color: 'bg-lime-50 text-lime-700 dark:bg-lime-950 dark:text-lime-300' },
    { icon: CalendarCheck, title: 'Attendance Tracking',   desc: 'Open sessions with one click. Mark P/L/A/E per student. Summary shows absence alerts.', color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' },
    { icon: Users,         title: 'Class Record',          desc: 'Spreadsheet-style grade book with inline editing, weighted finals, and transmutation.', color: 'bg-lime-50 text-lime-700 dark:bg-lime-950 dark:text-lime-300' },
];
</script>

<template>
    <Head title="FacultyHUB — Smart LMS for Faculty" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">

        <!-- Sticky nav -->
        <header class="sticky top-0 z-50 border-b border-border/60 bg-background/90 backdrop-blur-sm">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
                <!-- Logo -->
                <div class="flex items-center gap-2.5">
                    <div class="flex aspect-square size-8 items-center justify-center rounded-lg brand-gradient shadow-sm">
                        <svg viewBox="0 0 24 24" fill="none" class="size-5">
                            <circle cx="12" cy="12" r="2.5" fill="hsl(142 30% 12%)"/>
                            <circle cx="5"  cy="7"  r="1.8" fill="hsl(142 30% 12%)"/>
                            <circle cx="19" cy="7"  r="1.8" fill="hsl(142 30% 12%)"/>
                            <circle cx="5"  cy="17" r="1.8" fill="hsl(142 30% 12%)"/>
                            <circle cx="19" cy="17" r="1.8" fill="hsl(142 30% 12%)"/>
                            <line x1="12" y1="12" x2="5"  y2="7"  stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                            <line x1="12" y1="12" x2="19" y2="7"  stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                            <line x1="12" y1="12" x2="5"  y2="17" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                            <line x1="12" y1="12" x2="19" y2="17" stroke="hsl(142 30% 12%)" stroke-width="1.3"/>
                        </svg>
                    </div>
                    <span class="text-base font-bold tracking-tight">
                        Faculty<span class="brand-gradient-text">HUB</span>
                    </span>
                </div>

                <!-- Nav actions -->
                <nav class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="text-sm font-medium text-muted-foreground hover:text-foreground px-3 py-1.5 transition-colors"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button
                            v-if="canRegister"
                            size="sm"
                            class="brand-gradient border-0 font-semibold hover:opacity-90 shadow-sm"
                            style="color: hsl(142 30% 12%)"
                            as-child
                        >
                            <Link :href="register()">Get Started</Link>
                        </Button>
                    </template>
                </nav>
            </div>
        </header>

        <main class="flex-1">

            <!-- Hero section -->
            <section class="relative overflow-hidden py-24 text-center">
                <div class="absolute inset-0 brand-gradient opacity-10 pointer-events-none" />
                <div class="absolute top-0 right-0 h-96 w-96 rounded-full bg-accent/20 blur-3xl pointer-events-none -translate-y-1/2 translate-x-1/2" />
                <div class="absolute bottom-0 left-0 h-64 w-64 rounded-full bg-primary/10 blur-2xl pointer-events-none translate-y-1/2 -translate-x-1/2" />

                <div class="relative mx-auto max-w-4xl px-6">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary/30 bg-accent/50 px-4 py-1.5 text-xs font-semibold text-accent-foreground">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary inline-block animate-pulse" />
                        AI-Powered Learning Management System
                    </div>

                    <h1 class="mb-5 text-5xl font-extrabold tracking-tight leading-tight sm:text-6xl">
                        The smarter way to<br class="hidden sm:block" />
                        <span class="brand-gradient-text">run your class</span>
                    </h1>

                    <p class="mx-auto mb-8 max-w-xl text-lg text-muted-foreground leading-relaxed">
                        FacultyHUB brings modules, assignments, attendance, and grade records into one
                        clean platform — with Claude AI grading built right in.
                    </p>

                    <div class="flex flex-wrap justify-center gap-3">
                        <Button
                            size="lg"
                            class="brand-gradient border-0 font-semibold hover:opacity-90 shadow-md"
                            style="color: hsl(142 30% 12%)"
                            as-child
                        >
                            <Link :href="login()">
                                Start Teaching
                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
                        <Button variant="outline" size="lg" as-child v-if="canRegister">
                            <Link :href="register()">Create Account</Link>
                        </Button>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section class="border-t border-border/60 bg-muted/30 py-20">
                <div class="mx-auto max-w-6xl px-6">
                    <p class="mb-12 text-center text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                        Everything faculty need, nothing they don't
                    </p>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="f in features"
                            :key="f.title"
                            class="rounded-xl border border-border/60 bg-card p-5 space-y-3 hover:shadow-sm hover:border-primary/30 transition-all"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg" :class="f.color">
                                <component :is="f.icon" class="h-5 w-5" />
                            </div>
                            <h3 class="font-semibold">{{ f.title }}</h3>
                            <p class="text-sm text-muted-foreground leading-relaxed">{{ f.desc }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA band -->
            <section class="brand-gradient py-16">
                <div class="mx-auto max-w-2xl px-6 text-center">
                    <h2 class="mb-2 text-2xl font-bold" style="color: hsl(142 30% 12%)">Ready to get started?</h2>
                    <p class="mb-6 text-sm" style="color: hsl(142 25% 28%)">
                        Log in as faculty and start managing sections, modules, and AI-graded assignments.
                    </p>
                    <Button
                        size="lg"
                        class="bg-white/80 border border-[hsl(142_30%_40%)] font-semibold hover:bg-white shadow-sm"
                        style="color: hsl(142 30% 15%)"
                        as-child
                    >
                        <Link :href="login()">Log in to FacultyHUB</Link>
                    </Button>
                </div>
            </section>

        </main>

        <footer class="border-t border-border/60 py-5 text-center text-xs text-muted-foreground">
            FacultyHUB &copy; {{ new Date().getFullYear() }} &mdash; Laravel · Vue 3 · Inertia.js · Claude AI
        </footer>
    </div>
</template>
