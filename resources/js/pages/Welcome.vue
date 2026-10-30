<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    BarChart2,
    BookOpen,
    Bot,
    CalendarCheck,
    ClipboardList,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import CallToAction from '@/components/landing/CallToAction.vue';
import FeaturesSection from '@/components/landing/FeaturesSection.vue';
import type { LandingFeature } from '@/components/landing/FeaturesSection.vue';
import HeroSection from '@/components/landing/HeroSection.vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import LandingHeader from '@/components/landing/LandingHeader.vue';
import type { User } from '@/types';

withDefaults(defineProps<{ canRegister: boolean }>(), { canRegister: true });

const page = usePage();
const user = computed(() => (page.props.auth?.user ?? null) as User | null);
const role = computed(() => page.props.auth?.role);
const dashboardUrl = computed(() =>
    role.value === 'admin'
        ? '/admin/dashboard'
        : role.value === 'faculty'
          ? '/dashboard'
          : role.value === 'student'
            ? '/my-sections'
            : '/',
);
const dashboardLabel = computed(() =>
    role.value === 'admin'
        ? 'Admin Dashboard'
        : role.value === 'faculty'
          ? 'Faculty Dashboard'
          : role.value === 'student'
            ? 'Student Dashboard'
            : 'Dashboard',
);

const features: LandingFeature[] = [
    {
        icon: BookOpen,
        title: 'Learning modules',
        description:
            'Publish course materials and let students access modules and track their progress.',
        tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    },
    {
        icon: ClipboardList,
        title: 'Assignments & quizzes',
        description:
            'Create assessments, collect submissions, and keep deadlines visible in one place.',
        tone: 'bg-lime-50 text-lime-700 dark:bg-lime-950/50 dark:text-lime-300',
    },
    {
        icon: Bot,
        title: 'AI-assisted grading',
        description:
            'Use the existing AI grading workflows to support review of essays and code submissions.',
        tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    },
    {
        icon: BarChart2,
        title: 'Grades & progress',
        description:
            'Manage class records and give students a clear view of their academic progress.',
        tone: 'bg-lime-50 text-lime-700 dark:bg-lime-950/50 dark:text-lime-300',
    },
    {
        icon: CalendarCheck,
        title: 'Attendance tracking',
        description:
            'Record attendance efficiently and let students review their attendance history.',
        tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    },
    {
        icon: Users,
        title: 'Connected classrooms',
        description:
            'Keep faculty and students aligned through organized sections and shared course information.',
        tone: 'bg-lime-50 text-lime-700 dark:bg-lime-950/50 dark:text-lime-300',
    },
];

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head>
        <title>
            FacultyHUB LMS — Learning Management for Faculty & Students
        </title>
        <meta
            name="description"
            content="FacultyHUB LMS connects faculty and students through course materials, assignments, quizzes, attendance, grading, feedback, and academic progress."
        />
        <meta name="robots" content="index, follow" />
        <link rel="canonical" href="https://www.faculty-lms.online/" />
        <meta property="og:type" content="website" />
        <meta
            property="og:title"
            content="FacultyHUB LMS — Learning Management for Faculty & Students"
        />
        <meta
            property="og:description"
            content="Connect faculty and students through course materials, assessments, attendance, grading, feedback, and academic progress."
        />
        <meta property="og:url" content="https://www.faculty-lms.online/" />
        <meta property="og:site_name" content="FacultyHUB LMS" />
        <meta name="twitter:card" content="summary" />
        <meta
            name="twitter:title"
            content="FacultyHUB LMS — Learning Management for Faculty & Students"
        />
        <meta
            name="twitter:description"
            content="A learning management system for faculty and students with modules, assessments, attendance, grading, feedback, and academic progress."
        />
    </Head>

    <div
        class="flex min-h-screen min-w-0 flex-col bg-background text-foreground"
    >
        <LandingHeader
            :user="user"
            :can-register="canRegister"
            :dashboard-url="dashboardUrl"
            :dashboard-label="dashboardLabel"
            @logout="logout"
        />
        <main class="min-w-0 flex-1">
            <HeroSection
                :user="Boolean(user)"
                :can-register="canRegister"
                :dashboard-url="dashboardUrl"
                :dashboard-label="dashboardLabel"
            />
            <FeaturesSection :features="features" />
            <section
                id="audiences"
                class="border-b border-border/40 bg-background py-16 sm:py-20"
            >
                <div
                    class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-2 lg:px-8"
                >
                    <article
                        class="landing-fade-up rounded-2xl border border-border/70 bg-card p-6 shadow-sm sm:p-8"
                    >
                        <p
                            class="text-xs font-bold tracking-[0.2em] text-primary uppercase"
                        >
                            For faculty
                        </p>
                        <h2 class="mt-3 text-2xl font-bold tracking-tight">
                            Spend less time coordinating classwork.
                        </h2>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">
                            Organize sections, publish modules, create
                            assessments, monitor attendance, manage grades, and
                            review AI-assisted grading results from one
                            workspace.
                        </p>
                    </article>
                    <article
                        class="landing-fade-up rounded-2xl border border-border/70 bg-card p-6 shadow-sm sm:p-8"
                        style="animation-delay: 100ms"
                    >
                        <p
                            class="text-xs font-bold tracking-[0.2em] text-primary uppercase"
                        >
                            For students
                        </p>
                        <h2 class="mt-3 text-2xl font-bold tracking-tight">
                            Keep every course step in view.
                        </h2>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">
                            Access learning materials, submit assignments,
                            monitor deadlines, check attendance, and follow
                            academic progress in one focused place.
                        </p>
                    </article>
                </div>
            </section>
            <CallToAction
                :user="Boolean(user)"
                :dashboard-url="dashboardUrl"
                :dashboard-label="dashboardLabel"
            />
        </main>
        <LandingFooter />
    </div>
</template>
