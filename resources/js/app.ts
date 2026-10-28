import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import FacultyLayout from '@/layouts/FacultyLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import StudentLayout from '@/layouts/StudentLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name, page) => {
        switch (true) {
            case name === 'Welcome':
            case name === 'Error':
            case name === 'auth/Login':
                return null;

            case name.startsWith('auth/'):
                return AuthLayout;

            case name.startsWith('student/'):
                return StudentLayout;

            case name.startsWith('settings/') &&
                (page.props.auth as { role?: string } | undefined)?.role ===
                    'student':
                return StudentLayout;

            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];

            case (page.props.auth as { role?: string } | undefined)?.role ===
                'admin':
                return AdminLayout;

            case (page.props.auth as { role?: string } | undefined)?.role ===
                'faculty':
                return FacultyLayout;

            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
