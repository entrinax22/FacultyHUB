<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';

defineProps<{
    href: string;
    label: string;
}>();
</script>

<template>
    <Button
        variant="outline"
        size="sm"
        as-child
        class="min-h-10 w-fit shrink-0"
    >
        <Link :href="href">
            <ArrowLeft class="mr-1.5 size-4" aria-hidden="true" />
            {{ label }}
        </Link>
    </Button>
</template>
