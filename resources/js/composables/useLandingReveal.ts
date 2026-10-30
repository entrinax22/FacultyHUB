import { onBeforeUnmount, onMounted } from 'vue';
import type { Ref } from 'vue';

export function useLandingReveal(root: Ref<HTMLElement | null>) {
    let observer: IntersectionObserver | null = null;

    onMounted(() => {
        const elements = root.value?.querySelectorAll<HTMLElement>(
            '[data-landing-reveal]',
        );

        if (!elements?.length) {
            return;
        }

        if (typeof IntersectionObserver === 'undefined') {
            elements.forEach((element) => element.classList.add('is-visible'));

            return;
        }

        elements.forEach((element) =>
            element.classList.add('landing-reveal-pending'),
        );

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.classList.add('is-visible');
                    observer?.unobserve(entry.target);
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
        );

        elements.forEach((element) => observer?.observe(element));
    });

    onBeforeUnmount(() => observer?.disconnect());
}
