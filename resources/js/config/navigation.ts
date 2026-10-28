import {
    BarChart3,
    BookMarked,
    Bot,
    CalendarDays,
    GraduationCap,
    Home,
    Layers,
    LayoutGrid,
    Users,
} from 'lucide-vue-next';
import type { NavItem } from '@/types';

export const commonNavItems: NavItem[] = [
    { title: 'Home', href: '/', icon: Home },
];

export const teachingNavItems: NavItem[] = [
    { title: 'Subjects', href: '/subjects', icon: BookMarked },
    { title: 'Sections', href: '/sections', icon: Layers },
    { title: 'Students', href: '/students', icon: GraduationCap },
];

export const administrationNavItems: NavItem[] = [
    { title: 'Home', href: '/', icon: Home },
    { title: 'Dashboard', href: '/admin/dashboard', icon: LayoutGrid },
    { title: 'Users', href: '/admin/users', icon: Users },
    { title: 'Reports', href: '/admin/reports', icon: BarChart3 },
    { title: 'AI Settings', href: '/admin/ai-settings', icon: Bot },
    { title: 'Semesters', href: '/admin/semesters', icon: CalendarDays },
];
