<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

import {
    BookOpen,
    ClipboardList,
    Users,
    Bot,
    BarChart2,
    CalendarCheck,
    ArrowRight,
    User,
    LogOut,
    ChevronDown,
    Sparkles,
    CheckCircle2,
} from 'lucide-vue-next';

import { Button } from '@/components/ui/button';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import { login, register } from '@/routes';

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

/*
|--------------------------------------------------------------------------
| Inertia Page
|--------------------------------------------------------------------------
*/

const page = usePage();

/*
|--------------------------------------------------------------------------
| Authentication & Role
|--------------------------------------------------------------------------
*/

const user = computed(() => page.props.auth.user);

const userRole = computed(() => page.props.auth.role);

const isAdmin = computed(() => userRole.value === 'admin');

const isFaculty = computed(() => userRole.value === 'faculty');

const isStudent = computed(() => userRole.value === 'student');

/*
|--------------------------------------------------------------------------
| Role-Based Dashboard
|--------------------------------------------------------------------------
*/

const dashboardUrl = computed(() => {
    if (isAdmin.value) {
        return '/admin/dashboard';
    }

    if (isFaculty.value) {
        return '/dashboard';
    }

    if (isStudent.value) {
        return '/my-sections';
    }

    return '/';
});

const dashboardLabel = computed(() => {
    if (isAdmin.value) {
        return 'Admin Dashboard';
    }

    if (isFaculty.value) {
        return 'Faculty Dashboard';
    }

    if (isStudent.value) {
        return 'Student Dashboard';
    }

    return 'Dashboard';
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

const logout = () => {
    router.post('/logout');
};

/*
|--------------------------------------------------------------------------
| Features
|--------------------------------------------------------------------------
*/

const features = [
    {
        icon: BookOpen,
        title: 'Learning Modules',
        desc: 'Faculty can upload learning materials while students access modules, track their progress, and stay organized throughout the course.',
        color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    },
    {
        icon: ClipboardList,
        title: 'Assignments & Quizzes',
        desc: 'Faculty can create assignments, quizzes, essays, and programming activities while students submit their work and track deadlines.',
        color: 'bg-lime-50 text-lime-700 dark:bg-lime-950/50 dark:text-lime-300',
    },
    {
        icon: Bot,
        title: 'AI-Assisted Grading',
        desc: 'AI helps faculty evaluate essays and programming submissions while students receive feedback on their work.',
        color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    },
    {
        icon: BarChart2,
        title: 'Grades & Progress',
        desc: 'Faculty can manage class records and grades while students can view their scores, component grades, and overall academic progress.',
        color: 'bg-lime-50 text-lime-700 dark:bg-lime-950/50 dark:text-lime-300',
    },
    {
        icon: CalendarCheck,
        title: 'Attendance Tracking',
        desc: 'Faculty can record attendance efficiently while students can monitor their attendance history and stay aware of their participation.',
        color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    },
    {
        icon: Users,
        title: 'Connected Classrooms',
        desc: 'Bring faculty and students together in organized sections with shared modules, assessments, attendance, grades, and class information.',
        color: 'bg-lime-50 text-lime-700 dark:bg-lime-950/50 dark:text-lime-300',
    },
];

/*
|--------------------------------------------------------------------------
| Scroll Animations
|--------------------------------------------------------------------------
*/

let observer: IntersectionObserver | null = null;

onMounted(() => {
    /*
    |--------------------------------------------------------------------------
    | Smooth Scrolling
    |--------------------------------------------------------------------------
    */

    document.documentElement.style.scrollBehavior = 'smooth';

    /*
    |--------------------------------------------------------------------------
    | Scroll Reveal Observer
    |--------------------------------------------------------------------------
    */

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer?.unobserve(entry.target);
                }
            });
        },
        {
            threshold: 0.12,
            rootMargin: '0px 0px -50px 0px',
        },
    );

    document.querySelectorAll('.scroll-reveal').forEach((element) => {
        observer?.observe(element);
    });
});

onBeforeUnmount(() => {
    document.documentElement.style.scrollBehavior = '';

    observer?.disconnect();
});
</script>

<template>
    <Head title="FacultyLMS — Learning Management for Faculty & Students" />

    <div class="flex min-h-screen min-w-0 flex-col bg-background text-foreground">
        <!-- =========================================================
             NAVBAR
        ========================================================== -->
        <header
            class="sticky top-0 z-50 border-b border-border/60 bg-background/80 backdrop-blur-xl"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8"
            >
                <!-- Logo -->
                <Link
                    href="/"
                    class="group flex min-w-0 shrink-0 items-center gap-2.5"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl brand-gradient shadow-sm transition-all duration-300 group-hover:scale-105 group-hover:rotate-3"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            class="size-5 transition-transform duration-300 group-hover:scale-110"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="2.5"
                                fill="hsl(142 30% 12%)"
                            />

                            <circle
                                cx="5"
                                cy="7"
                                r="1.8"
                                fill="hsl(142 30% 12%)"
                            />

                            <circle
                                cx="19"
                                cy="7"
                                r="1.8"
                                fill="hsl(142 30% 12%)"
                            />

                            <circle
                                cx="5"
                                cy="17"
                                r="1.8"
                                fill="hsl(142 30% 12%)"
                            />

                            <circle
                                cx="19"
                                cy="17"
                                r="1.8"
                                fill="hsl(142 30% 12%)"
                            />

                            <line
                                x1="12"
                                y1="12"
                                x2="5"
                                y2="7"
                                stroke="hsl(142 30% 12%)"
                                stroke-width="1.3"
                            />

                            <line
                                x1="12"
                                y1="12"
                                x2="19"
                                y2="7"
                                stroke="hsl(142 30% 12%)"
                                stroke-width="1.3"
                            />

                            <line
                                x1="12"
                                y1="12"
                                x2="5"
                                y2="17"
                                stroke="hsl(142 30% 12%)"
                                stroke-width="1.3"
                            />

                            <line
                                x1="12"
                                y1="12"
                                x2="19"
                                y2="17"
                                stroke="hsl(142 30% 12%)"
                                stroke-width="1.3"
                            />
                        </svg>
                    </div>

                    <span
                        class="truncate text-base font-bold tracking-tight sm:text-lg"
                    >
                        Faculty<span class="brand-gradient-text">LMS</span>
                    </span>
                </Link>

                <!-- Navigation -->
                <nav class="flex min-w-0 items-center gap-1 sm:gap-2">
                    <!-- Public Navigation -->
                    <div class="mr-2 hidden items-center gap-5 md:flex">
                        <a
                            href="#features"
                            class="text-sm font-medium text-muted-foreground transition-colors duration-200 hover:text-foreground"
                        >
                            Features
                        </a>
                    </div>

                    <!-- =====================================================
                         AUTHENTICATED USER
                    ====================================================== -->
                    <template v-if="user">
                        <!-- Role-Based Dashboard -->
                        <Button
                            variant="ghost"
                            size="sm"
                            as-child
                            class="hidden transition-all duration-200 hover:-translate-y-0.5 sm:inline-flex"
                        >
                            <Link :href="dashboardUrl">
                                {{ dashboardLabel }}
                            </Link>
                        </Button>

                        <!-- User Dropdown -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="outline"
                                    class="ml-1 flex items-center gap-2 rounded-full px-2.5 transition-all duration-200 hover:scale-[1.02] sm:px-3"
                                >
                                    <!-- Avatar -->
                                    <div
                                        class="flex size-7 shrink-0 items-center justify-center rounded-full brand-gradient transition-transform duration-300 hover:scale-105"
                                    >
                                        <User
                                            class="size-4"
                                            style="color: hsl(142 30% 12%)"
                                        />
                                    </div>

                                    <!-- User Name -->
                                    <span
                                        class="hidden max-w-32 truncate text-sm font-medium sm:inline"
                                    >
                                        {{ user.name }}
                                    </span>

                                    <ChevronDown
                                        class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                    />
                                </Button>
                            </DropdownMenuTrigger>

                            <DropdownMenuContent
                                align="end"
                                class="w-60"
                            >
                                <!-- User Information -->
                                <div class="px-3 py-2.5">
                                    <p
                                        class="truncate text-sm font-semibold"
                                    >
                                        {{ user.name }}
                                    </p>

                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ user.email }}
                                    </p>

                                    <!-- Role Badge -->
                                    <div class="mt-2">
                                        <!-- Admin -->
                                        <span
                                            v-if="isAdmin"
                                            class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300"
                                        >
                                            Administrator
                                        </span>

                                        <!-- Faculty -->
                                        <span
                                            v-else-if="isFaculty"
                                            class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                        >
                                            Faculty
                                        </span>

                                        <!-- Student -->
                                        <span
                                            v-else-if="isStudent"
                                            class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                                        >
                                            Student
                                        </span>
                                    </div>
                                </div>

                                <DropdownMenuSeparator />

                                <!-- Profile -->
                                <DropdownMenuItem as-child>
                                    <Link
                                        href="/profile"
                                        class="flex cursor-pointer items-center gap-2"
                                    >
                                        <User class="size-4" />
                                        <span>Profile</span>
                                    </Link>
                                </DropdownMenuItem>

                                <!-- Dashboard -->
                                <DropdownMenuItem as-child>
                                    <Link
                                        :href="dashboardUrl"
                                        class="flex cursor-pointer items-center gap-2"
                                    >
                                        <BarChart2 class="size-4" />

                                        <span>
                                            {{ dashboardLabel }}
                                        </span>
                                    </Link>
                                </DropdownMenuItem>

                                <DropdownMenuSeparator />

                                <!-- Logout -->
                                <DropdownMenuItem
                                    class="cursor-pointer text-destructive focus:text-destructive"
                                    @click="logout"
                                >
                                    <LogOut class="size-4" />
                                    <span>Log out</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>

                    <!-- =====================================================
                         GUEST
                    ====================================================== -->
                    <template v-else>
                        <Button
                            variant="ghost"
                            size="sm"
                            as-child
                            class="transition-all duration-200 hover:-translate-y-0.5"
                        >
                            <Link :href="login()">
                                Log in
                            </Link>
                        </Button>

                        <Button
                            v-if="canRegister"
                            size="sm"
                            class="brand-gradient border-0 font-semibold shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:opacity-90 hover:shadow-md"
                            style="color: hsl(142 30% 12%)"
                            as-child
                        >
                            <Link :href="register()">
                                <span class="hidden xs:inline">
                                    Get Started
                                </span>

                                <span class="xs:hidden">
                                    Sign Up
                                </span>
                            </Link>
                        </Button>
                    </template>
                </nav>
            </div>
        </header>

        <!-- =========================================================
             MAIN
        ========================================================== -->
        <main class="min-w-0 flex-1">
            <!-- =====================================================
                 HERO
            ====================================================== -->
            <section
                class="relative isolate overflow-hidden border-b border-border/40"
            >
                <!-- Background -->
                <div
                    class="glow-animation absolute inset-0 -z-10 brand-gradient"
                ></div>

                <div
                    class="float-animation absolute -right-32 -top-32 -z-10 size-[500px] rounded-full bg-emerald-400/10 blur-3xl"
                ></div>

                <div
                    class="float-animation absolute -bottom-32 -left-32 -z-10 size-[400px] rounded-full bg-lime-400/10 blur-3xl"
                    style="animation-delay: 1.5s"
                ></div>

                <div
                    class="mx-auto max-w-7xl px-4 pb-20 pt-16 text-center sm:px-6 sm:pb-24 sm:pt-24 lg:px-8 lg:pt-28"
                >
                    <!-- Badge -->
                    <div
                        class="fade-up mx-auto mb-7 inline-flex max-w-full items-center gap-2 rounded-full border border-primary/20 bg-background/70 px-3 py-2 text-[11px] font-semibold shadow-sm backdrop-blur sm:px-4 sm:text-xs"
                    >
                        <Sparkles
                            class="size-3.5 shrink-0 text-primary"
                        />

                        <span class="truncate">
                            Learning Management System for Faculty & Students
                        </span>

                        <span
                            class="h-1.5 w-1.5 shrink-0 animate-pulse rounded-full bg-primary"
                        ></span>
                    </div>

                    <!-- Heading -->
                    <h1
                        class="fade-up mx-auto max-w-4xl text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-6xl lg:text-7xl"
                        style="animation-delay: 100ms"
                    >
                        One platform for
                        <span class="brand-gradient-text">
                            teaching and learning
                        </span>
                    </h1>

                    <!-- Description -->
                    <p
                        class="fade-up mx-auto mt-6 max-w-2xl text-sm leading-6 text-muted-foreground sm:mt-7 sm:text-lg sm:leading-7"
                        style="animation-delay: 200ms"
                    >
                        FacultyLMS connects faculty and students in one
                        streamlined learning environment — from course
                        materials and assessments to attendance, grading,
                        feedback, and academic progress.
                    </p>

                    <!-- =================================================
                         ROLE-BASED CTA
                    ================================================== -->
                    <div
                        class="fade-up mt-8 flex flex-col justify-center gap-3 sm:mt-9 sm:flex-row sm:flex-wrap"
                        style="animation-delay: 300ms"
                    >
                        <!-- Guest -->
                        <template v-if="!user">
                            <Button
                                size="lg"
                                class="brand-gradient w-full border-0 px-6 font-semibold shadow-lg transition-all duration-300 hover:-translate-y-1 hover:opacity-90 hover:shadow-xl sm:w-auto"
                                style="color: hsl(142 30% 12%)"
                                as-child
                            >
                                <Link :href="login()">
                                    Start Teaching

                                    <ArrowRight
                                        class="ml-2 size-4 transition-transform duration-300"
                                    />
                                </Link>
                            </Button>

                            <Button
                                v-if="canRegister"
                                variant="outline"
                                size="lg"
                                class="w-full px-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-md sm:w-auto"
                                as-child
                            >
                                <Link :href="register()">
                                    Create Account
                                </Link>
                            </Button>
                        </template>

                        <!-- Admin -->
                        <template v-else-if="isAdmin">
                            <Button
                                size="lg"
                                class="brand-gradient w-full border-0 px-6 font-semibold shadow-lg transition-all duration-300 hover:-translate-y-1 hover:opacity-90 hover:shadow-xl sm:w-auto"
                                style="color: hsl(142 30% 12%)"
                                as-child
                            >
                                <Link :href="dashboardUrl">
                                    Admin Dashboard

                                    <ArrowRight class="ml-2 size-4" />
                                </Link>
                            </Button>
                        </template>

                        <!-- Faculty -->
                        <template v-else-if="isFaculty">
                            <Button
                                size="lg"
                                class="brand-gradient w-full border-0 px-6 font-semibold shadow-lg transition-all duration-300 hover:-translate-y-1 hover:opacity-90 hover:shadow-xl sm:w-auto"
                                style="color: hsl(142 30% 12%)"
                                as-child
                            >
                                <Link :href="dashboardUrl">
                                    Start Teaching

                                    <ArrowRight class="ml-2 size-4" />
                                </Link>
                            </Button>
                        </template>

                        <!-- Student -->
                        <template v-else-if="isStudent">
                            <Button
                                size="lg"
                                class="brand-gradient w-full border-0 px-6 font-semibold shadow-lg transition-all duration-300 hover:-translate-y-1 hover:opacity-90 hover:shadow-xl sm:w-auto"
                                style="color: hsl(142 30% 12%)"
                                as-child
                            >
                                <Link :href="dashboardUrl">
                                    Start Learning

                                    <ArrowRight class="ml-2 size-4" />
                                </Link>
                            </Button>
                        </template>
                    </div>

                    <!-- Trust Indicators -->
                    <div
                        class="fade-up mx-auto mt-9 flex max-w-2xl flex-wrap justify-center gap-x-6 gap-y-3 text-xs text-muted-foreground sm:mt-10"
                        style="animation-delay: 400ms"
                    >
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2
                                class="size-3.5 text-primary"
                            />
                            Built for faculty and students
                        </span>

                        <span class="flex items-center gap-1.5">
                            <CheckCircle2
                                class="size-3.5 text-primary"
                            />
                            AI-assisted learning and grading
                        </span>

                        <span class="flex items-center gap-1.5">
                            <CheckCircle2
                                class="size-3.5 text-primary"
                            />
                            Centralized academic records
                        </span>
                    </div>
                </div>
            </section>

            <!-- =========================================================
                 FEATURES
            ========================================================== -->
            <section
                id="features"
                class="scroll-mt-16 bg-muted/30 py-16 sm:py-24"
            >
                <div
                    class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
                >
                    <!-- Section Heading -->
                    <div
                        class="scroll-reveal mx-auto mb-10 max-w-2xl text-center sm:mb-12"
                    >
                        <p
                            class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary"
                        >
                            Everything in one place
                        </p>

                        <h2
                            class="text-3xl font-bold tracking-tight sm:text-4xl"
                        >
                            Everything your class needs
                        </h2>

                        <p
                            class="mt-4 text-sm leading-6 text-muted-foreground sm:text-base"
                        >
                            FacultyLMS gives faculty the tools to manage
                            their classes and gives students a simple place
                            to learn, submit work, and track their academic
                            progress.
                        </p>
                    </div>

                    <!-- Feature Cards -->
                    <div
                        class="grid gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3"
                    >
                        <div
                            v-for="(f, index) in features"
                            :key="f.title"
                            class="scroll-reveal group rounded-2xl border border-border/60 bg-card p-5 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:border-primary/30 hover:shadow-lg sm:p-6"
                            :style="{
                                transitionDelay: `${index * 100}ms`,
                            }"
                        >
                            <div
                                class="mb-5 flex size-11 items-center justify-center rounded-xl transition-all duration-300 group-hover:scale-110 group-hover:rotate-3"
                                :class="f.color"
                            >
                                <component
                                    :is="f.icon"
                                    class="size-5 transition-transform duration-300 group-hover:scale-110"
                                />
                            </div>

                            <h3 class="text-base font-semibold">
                                {{ f.title }}
                            </h3>

                            <p
                                class="mt-2 text-sm leading-6 text-muted-foreground"
                            >
                                {{ f.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- =========================================================
                 FACULTY + STUDENT EXPERIENCE
            ========================================================== -->
            <section class="border-b border-border/40 bg-background py-16 sm:py-20">
                <div
                    class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
                >
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- Faculty -->
                        <div
                            class="scroll-reveal rounded-2xl border border-border/60 bg-card p-6 shadow-sm sm:p-8"
                        >
                            <div
                                class="mb-5 flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                            >
                                <Users class="size-5" />
                            </div>

                            <p
                                class="text-xs font-bold uppercase tracking-[0.2em] text-primary"
                            >
                                For Faculty
                            </p>

                            <h3
                                class="mt-2 text-2xl font-bold tracking-tight"
                            >
                                Manage your classes with less effort
                            </h3>

                            <p
                                class="mt-3 text-sm leading-6 text-muted-foreground"
                            >
                                Organize sections, publish learning
                                modules, create assessments, monitor
                                attendance, manage grades, and use AI-assisted
                                grading tools from one centralized workspace.
                            </p>

                            <div class="mt-6 space-y-3">
                                <div
                                    class="flex items-start gap-2.5 text-sm"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />

                                    <span>
                                        Manage modules and course materials
                                    </span>
                                </div>

                                <div
                                    class="flex items-start gap-2.5 text-sm"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />

                                    <span>
                                        Create and manage assessments
                                    </span>
                                </div>

                                <div
                                    class="flex items-start gap-2.5 text-sm"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />

                                    <span>
                                        Track attendance and grades
                                    </span>
                                </div>

                                <div
                                    class="flex items-start gap-2.5 text-sm"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />

                                    <span>
                                        Review AI-assisted grading results
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Students -->
                        <div
                            class="scroll-reveal rounded-2xl border border-border/60 bg-card p-6 shadow-sm sm:p-8"
                            style="transition-delay: 100ms"
                        >
                            <div
                                class="mb-5 flex size-11 items-center justify-center rounded-xl bg-lime-50 text-lime-700 dark:bg-lime-950/50 dark:text-lime-300"
                            >
                                <BookOpen class="size-5" />
                            </div>

                            <p
                                class="text-xs font-bold uppercase tracking-[0.2em] text-primary"
                            >
                                For Students
                            </p>

                            <h3
                                class="mt-2 text-2xl font-bold tracking-tight"
                            >
                                Keep your learning organized
                            </h3>

                            <p
                                class="mt-3 text-sm leading-6 text-muted-foreground"
                            >
                                Access course materials, complete
                                assignments and quizzes, monitor deadlines,
                                check attendance, and keep track of your
                                academic progress in one place.
                            </p>

                            <div class="mt-6 space-y-3">
                                <div
                                    class="flex items-start gap-2.5 text-sm"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />

                                    <span>
                                        Access modules and learning materials
                                    </span>
                                </div>

                                <div
                                    class="flex items-start gap-2.5 text-sm"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />

                                    <span>
                                        Submit assignments and take quizzes
                                    </span>
                                </div>

                                <div
                                    class="flex items-start gap-2.5 text-sm"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />

                                    <span>
                                        Monitor grades and academic progress
                                    </span>
                                </div>

                                <div
                                    class="flex items-start gap-2.5 text-sm"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                    />

                                    <span>
                                        View attendance and submission status
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- =========================================================
                 CTA
            ========================================================== -->
            <section
                id="get-started"
                class="scroll-mt-16 brand-gradient py-16 sm:py-20"
            >
                <div
                    class="scroll-reveal mx-auto max-w-3xl px-4 text-center sm:px-6"
                >
                    <div
                        class="float-animation mx-auto mb-5 flex size-12 items-center justify-center rounded-2xl bg-white/60 shadow-sm"
                    >
                        <Sparkles
                            class="size-6"
                            style="color: hsl(142 30% 15%)"
                        />
                    </div>

                    <h2
                        class="text-3xl font-bold tracking-tight sm:text-4xl"
                        style="color: hsl(142 30% 12%)"
                    >
                        A better learning experience for everyone
                    </h2>

                    <p
                        class="mx-auto mt-3 max-w-xl text-sm leading-6"
                        style="color: hsl(142 25% 28%)"
                    >
                        FacultyLMS brings teaching, learning, assessments,
                        attendance, grading, feedback, and academic progress
                        together in one place.
                    </p>

                    <!-- CTA based on authentication -->
                    <div class="mt-7">
                        <!-- Guest -->
                        <Button
                            v-if="!user"
                            size="lg"
                            class="w-full border border-[hsl(142_30%_40%)] bg-white/90 px-6 font-semibold shadow-md transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-xl sm:w-auto"
                            style="color: hsl(142 30% 15%)"
                            as-child
                        >
                            <Link :href="login()">
                                Log in to FacultyLMS

                                <ArrowRight
                                    class="ml-2 size-4 transition-transform duration-300"
                                />
                            </Link>
                        </Button>

                        <!-- Authenticated User -->
                        <Button
                            v-else
                            size="lg"
                            class="w-full border border-[hsl(142_30%_40%)] bg-white/90 px-6 font-semibold shadow-md transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-xl sm:w-auto"
                            style="color: hsl(142 30% 15%)"
                            as-child
                        >
                            <Link :href="dashboardUrl">
                                {{ dashboardLabel }}

                                <ArrowRight
                                    class="ml-2 size-4 transition-transform duration-300"
                                />
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>
        </main>

        <!-- =========================================================
             FOOTER
        ========================================================== -->
        <footer
            class="border-t border-border/60 bg-background py-6"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 text-center text-xs text-muted-foreground sm:flex-row sm:px-6 sm:text-left lg:px-8"
            >
                <p>
                    FacultyLMS © {{ new Date().getFullYear() }}
                    · Teaching & Learning Platform
                </p>

                <p>
                    Empowering faculty and students through connected learning
                </p>
            </div>
        </footer>
    </div>
</template>

<style>
/*
|--------------------------------------------------------------------------
| Smooth Scrolling
|--------------------------------------------------------------------------
*/

html {
    scroll-behavior: smooth;
}

/*
|--------------------------------------------------------------------------
| Scroll Reveal
|--------------------------------------------------------------------------
*/

.scroll-reveal {
    opacity: 0;
    transform: translateY(30px);

    transition:
        opacity 0.7s ease,
        transform 0.7s ease;
}

.scroll-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
}

/*
|--------------------------------------------------------------------------
| Fade Up
|--------------------------------------------------------------------------
*/

.fade-up {
    animation: fadeUp 0.8s ease-out both;
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(25px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/*
|--------------------------------------------------------------------------
| Fade In
|--------------------------------------------------------------------------
*/

.fade-in {
    animation: fadeIn 0.8s ease-out both;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

/*
|--------------------------------------------------------------------------
| Floating Animation
|--------------------------------------------------------------------------
*/

.float-animation {
    animation: floating 5s ease-in-out infinite;
}

@keyframes floating {
    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-8px);
    }
}

/*
|--------------------------------------------------------------------------
| Glow Animation
|--------------------------------------------------------------------------
*/

.glow-animation {
    animation: glow 3s ease-in-out infinite;
}

@keyframes glow {
    0%,
    100% {
        opacity: 0.08;
    }

    50% {
        opacity: 0.16;
    }
}

/*
|--------------------------------------------------------------------------
| Reduced Motion Accessibility
|--------------------------------------------------------------------------
*/

@media (prefers-reduced-motion: reduce) {
    html {
        scroll-behavior: auto;
    }

    .scroll-reveal,
    .fade-up,
    .fade-in,
    .float-animation,
    .glow-animation {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
        transition: none !important;
    }
}
</style>