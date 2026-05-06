import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

export function initializeFlashToast(): void {
    router.on('navigate', (event) => {
        const flash = (event.detail.page.props as any).flash as
            | { success?: string; error?: string }
            | undefined;

        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    });
}
