<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        processing?: boolean;
        disabled?: boolean;
        submitLabel?: string;
        processingLabel?: string;
        cancelHref?: string;
        cancelLabel?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        processing: false,
        disabled: false,
        submitLabel: 'Save changes',
        processingLabel: 'Saving...',
        cancelHref: undefined,
        cancelLabel: 'Cancel',
        class: undefined,
    },
);

function preventCancel(event: MouseEvent) {
    if (props.processing || props.disabled) {
        event.preventDefault();
    }
}
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col-reverse gap-2 border-t border-border/70 pt-5 sm:flex-row sm:justify-end sm:gap-3',
                $props.class,
            )
        "
    >
        <Button
            type="submit"
            :disabled="processing || disabled"
            :aria-busy="processing"
        >
            <Spinner v-if="processing" aria-hidden="true" />
            <span>{{ processing ? processingLabel : submitLabel }}</span>
        </Button>

        <Button v-if="cancelHref" variant="outline" as-child>
            <Link
                :href="cancelHref"
                :aria-disabled="processing || disabled"
                @click="preventCancel"
            >
                {{ cancelLabel }}
            </Link>
        </Button>

        <slot name="cancel" />
    </div>
</template>
