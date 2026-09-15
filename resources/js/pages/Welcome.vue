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
        title: 'Module Management',
        desc: 'Upload PDFs and learning files while students track their reading progress with a visual completion bar.',
        color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    },
    {
        icon: ClipboardList,
        title: 'Assignments & Quizzes',
        desc: 'Create essays, MCQs, and programming problems with automatic grading for objective assessments.',
        color: 'bg-lime-50 text-lime-700 dark:bg-lime-950/50 dark:text-lime-300',
    },
    {
        icon: Bot,
        title: 'AI-Powered Grading',
        desc: 'Use Claude AI to assist with essay and code grading while keeping faculty in control of final results.',
        color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    },
    {
        icon: BarChart2,
        title: 'Plagiarism Detection',
        desc: 'Batch-compare essay submissions and quickly identify submissions with high similarity.',
        color: 'bg-lime-50 text-lime-700 dark:bg-lime-950/50 dark:text-lime-300',
    },
    {
        icon: CalendarCheck,
        title: 'Attendance Tracking',
        desc: 'Open attendance sessions, record P/L/A/E statuses, and quickly identify students with absence concerns.',
        color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    },
    {
        icon: Users,
        title: 'Class Record',
        desc: 'Manage grades using a spreadsheet-style grade book with weighted components and transmutation.',
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

    document
        .querySelectorAll('.scroll-reveal')
        .forEach((element) => {
            observer?.observe(element);
        });
});

onBeforeUnmount(() => {
    document.documentElement.style.scrollBehavior = '';

    observer?.disconnect();
});
</script>

<template>
    <Head title="FacultyHUB — Smart LMS for Faculty" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <!-- =========================================================
             NAVBAR
        ========================================================== -->

        <header
            class="sticky top-0 z-50 border-b border-border/60 bg-background/80 backdrop-blur-xl"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-6 lg:px-8"
            >
                <!-- Logo -->

                <Link
                    href="/"
                    class="group flex items-center gap-2.5"
                >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl brand-gradient shadow-sm transition-all duration-300 group-hover:scale-105 group-hover:rotate-3"
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

                    <span class="text-lg font-bold tracking-tight">
                        Faculty<span class="brand-gradient-text">HUB</span>
                    </span>
                </Link>

                <!-- Navigation -->

                <nav class="flex items-center gap-2">
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
                                        class="flex size-7 items-center justify-center rounded-full brand-gradient transition-transform duration-300 hover:scale-105"
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
                                        class="size-4 text-muted-foreground transition-transform duration-200"
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

                                <!-- Role-Based Dashboard -->

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
                                Get Started
                            </Link>
                        </Button>
                    </template>
                </nav>
            </div>
        </header>

        <!-- =========================================================
             MAIN
        ========================================================== -->

        <main class="flex-1">
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
                    class="mx-auto max-w-7xl px-6 pb-24 pt-20 text-center sm:pt-28 lg:px-8"
                >
                    <!-- Badge -->

                    <div
                        class="fade-up mb-7 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-background/70 px-4 py-2 text-xs font-semibold shadow-sm backdrop-blur"
                    >
                        <Sparkles class="size-3.5 text-primary" />

                        <span>
                            AI-Powered Learning Management System
                        </span>

                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-primary"
                        ></span>
                    </div>

                    <!-- Heading -->

                    <h1
                        class="fade-up mx-auto max-w-4xl text-5xl font-extrabold leading-[1.05] tracking-tight sm:text-6xl lg:text-7xl"
                        style="animation-delay: 100ms"
                    >
                        The smarter way to

                        <span class="brand-gradient-text">
                            run your class
                        </span>
                    </h1>

                    <!-- Description -->

                    <p
                        class="fade-up mx-auto mt-7 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg"
                        style="animation-delay: 200ms"
                    >
                        FacultyHUB brings modules, assignments, quizzes,
                        attendance, grading, and class records into one
                        streamlined platform — with AI assistance built right in.
                    </p>

                    <!-- =================================================
                         ROLE-BASED CTA
                    ================================================== -->

                    <div
                        class="fade-up mt-9 flex flex-wrap justify-center gap-3"
                        style="animation-delay: 300ms"
                    >
                        <!-- Guest -->

                        <template v-if="!user">
                            <Button
                                size="lg"
                                class="brand-gradient border-0 px-6 font-semibold shadow-lg transition-all duration-300 hover:-translate-y-1 hover:opacity-90 hover:shadow-xl"
                                style="color: hsl(142 30% 12%)"
                                as-child
                            >
                                <Link :href="login()">
                                    Start Teaching

                                    <ArrowRight
                                        class="ml-2 size-4 transition-transform duration-300 group-hover:translate-x-1"
                                    />
                                </Link>
                            </Button>

                            <Button
                                v-if="canRegister"
                                variant="outline"
                                size="lg"
                                class="px-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-md"
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
                                class="brand-gradient border-0 px-6 font-semibold shadow-lg transition-all duration-300 hover:-translate-y-1 hover:opacity-90 hover:shadow-xl"
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
                                class="brand-gradient border-0 px-6 font-semibold shadow-lg transition-all duration-300 hover:-translate-y-1 hover:opacity-90 hover:shadow-xl"
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
                                class="brand-gradient border-0 px-6 font-semibold shadow-lg transition-all duration-300 hover:-translate-y-1 hover:opacity-90 hover:shadow-xl"
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
                        class="fade-up mx-auto mt-10 flex max-w-xl flex-wrap justify-center gap-x-6 gap-y-3 text-xs text-muted-foreground"
                        style="animation-delay: 400ms"
                    >
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 class="size-3.5 text-primary" />

                            Faculty-focused
                        </span>

                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 class="size-3.5 text-primary" />

                            AI-assisted grading
                        </span>

                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 class="size-3.5 text-primary" />

                            Centralized class records
                        </span>
                    </div>
                </div>
            </section>

            <!-- =========================================================
                 FEATURES
            ========================================================== -->

            <section
                id="features"
                class="scroll-mt-16 bg-muted/30 py-20 sm:py-24"
            >
                <div
                    class="mx-auto max-w-7xl px-6 lg:px-8"
                >
                    <!-- Section Heading -->

                    <div
                        class="scroll-reveal mx-auto mb-12 max-w-2xl text-center"
                    >
                        <p
                            class="mb-3 text-xs font-bold uppercase tracking-[0.2em] text-primary"
                        >
                            Everything in one place
                        </p>

                        <h2
                            class="text-3xl font-bold tracking-tight sm:text-4xl"
                        >
                            Tools built for modern faculty
                        </h2>

                        <p
                            class="mt-4 text-sm leading-6 text-muted-foreground sm:text-base"
                        >
                            Spend less time managing administrative work and
                            more time focusing on your students.
                        </p>
                    </div>

                    <!-- Feature Cards -->

                    <div
                        class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="(f, index) in features"
                            :key="f.title"
                            class="scroll-reveal group rounded-2xl border border-border/60 bg-card p-6 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:border-primary/30 hover:shadow-lg"
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
                 CTA
            ========================================================== -->

            <section
                id="get-started"
                class="scroll-mt-16 brand-gradient py-16 sm:py-20"
            >
                <div
                    class="scroll-reveal mx-auto max-w-3xl px-6 text-center"
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
                        class="text-3xl font-bold tracking-tight"
                        style="color: hsl(142 30% 12%)"
                    >
                        Ready to simplify your teaching?
                    </h2>

                    <p
                        class="mx-auto mt-3 max-w-xl text-sm leading-6"
                        style="color: hsl(142 25% 28%)"
                    >
                        FacultyHUB brings modules, assessments, attendance,
                        grading, and class records together in one place.
                    </p>

                    <!-- CTA based on authentication -->

                    <div class="mt-7">
                        <!-- Guest -->

                        <Button
                            v-if="!user"
                            size="lg"
                            class="border border-[hsl(142_30%_40%)] bg-white/90 px-6 font-semibold shadow-md transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-xl"
                            style="color: hsl(142 30% 15%)"
                            as-child
                        >
                            <Link :href="login()">
                                Log in to FacultyHUB

                                <ArrowRight
                                    class="ml-2 size-4 transition-transform duration-300"
                                />
                            </Link>
                        </Button>

                        <!-- Authenticated User -->

                        <Button
                            v-else
                            size="lg"
                            class="border border-[hsl(142_30%_40%)] bg-white/90 px-6 font-semibold shadow-md transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-xl"
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
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 text-xs text-muted-foreground sm:flex-row lg:px-8"
            >
                <p>
                    FacultyHUB © {{ new Date().getFullYear() }}
                </p>

                <p>
                    Laravel · Vue 3 · Inertia.js · Claude AI
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