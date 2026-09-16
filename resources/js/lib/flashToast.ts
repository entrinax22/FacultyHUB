import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

export function initializeFlashToast(): void {
    let submittedMethod: string | null = null;

    router.on('start', (event) => {
        const method = event.detail.visit.method.toUpperCase();

        submittedMethod = method === 'GET' ? null : method;
    });

    router.on('success', (event) => {
        if (!submittedMethod) {
            return;
        }

        const flash = (event.detail.page.props as any).flash as
            | {
                  success?: string;
                  error?: string;
                  warning?: string;
              }
            | undefined;

        if (flash?.success) {
            toast.success(flash.success);
        } else if (flash?.error) {
            toast.error(flash.error);
        } else if (flash?.warning) {
            toast.warning(flash.warning);
        } else {
            toast.success('Changes saved successfully.');
        }

        submittedMethod = null;
    });

    router.on('error', (event) => {
        if (!submittedMethod) {
            return;
        }

        const errors = Object.values(event.detail.errors ?? {}).flat();
        const firstError = errors[0];

        toast.error(
            typeof firstError === 'string'
                ? firstError
                : 'The submission could not be completed.'
        );

        submittedMethod = null;
    });
}