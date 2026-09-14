<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Bot, CheckCircle2, KeyRound, Power, RotateCw, SlidersHorizontal } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Settings = {
    enabled: boolean;
    temperature: number;
    max_output_tokens: number;
    include_assignment_instructions: boolean;
    include_rubric: boolean;
    context_enabled: boolean;
    global_context: string;
    essay_context: string;
    code_context: string;
    plagiarism_context: string;
};

type Provider = {
    id: number;
    name: string;
    provider: string;
    models: string[];
    selected_model: string;
    enabled: boolean;
    status: 'active' | 'inactive' | 'exhausted';
    status_message: string | null;
    status_checked_at: string | null;
    quota_exhausted_at: string | null;
    has_api_key: boolean;
    connected: boolean;
};

type ProviderDraft = {
    api_key: string;
    clear_api_key: boolean;
    models: string[];
    selected_model: string;
};

const props = defineProps<{
    settings: Settings;
    providers: Provider[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'AI Settings', href: '/admin/ai-settings' },
        ],
    },
});

const activeProviderId = ref(props.providers.find((provider) => provider.enabled)?.id ?? props.providers[0]?.id);

const contextForm = useForm({
    enabled: props.settings.enabled,
    temperature: props.settings.temperature,
    max_output_tokens: props.settings.max_output_tokens,
    include_assignment_instructions: props.settings.include_assignment_instructions,
    include_rubric: props.settings.include_rubric,
    context_enabled: props.settings.context_enabled,
    global_context: props.settings.global_context,
    essay_context: props.settings.essay_context,
    code_context: props.settings.code_context,
    plagiarism_context: props.settings.plagiarism_context,
});

const providerDrafts = reactive<{ [key: number]: ProviderDraft }>(
    Object.fromEntries(
        props.providers.map((provider) => [
            provider.id,
            {
                api_key: '',
                clear_api_key: false,
                models: [...provider.models],
                selected_model: provider.selected_model,
            },
        ]),
    ),
);

const newModel = ref('');
const selectedProvider = computed(() => props.providers.find((provider) => provider.id === activeProviderId.value) ?? props.providers[0]);
const selectedDraft = computed(() => providerDrafts[selectedProvider.value.id]);

function statusVariant(status: Provider['status']) {
    if (status === 'active') {
        return 'default';
    }

    if (status === 'exhausted') {
        return 'destructive';
    }

    return 'secondary';
}

function saveProvider(provider: Provider) {
    const draft = providerDrafts[provider.id];

    router.put(`/admin/ai-settings/providers/${provider.id}`, {
        ...draft,
        models: draft.models.join('\n'),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            draft.api_key = '';
            draft.clear_api_key = false;
        },
    });
}

function addModel(provider: ProviderDraft, model: string) {
    const normalizedModel = model.trim();

    if (!normalizedModel || provider.models.includes(normalizedModel)) {
        return;
    }

    provider.models.push(normalizedModel);
    provider.selected_model = normalizedModel;
}

function addCustomModel() {
    addModel(selectedDraft.value, newModel.value);
    newModel.value = '';
}

function toggleProvider(provider: Provider) {
    router.post(`/admin/ai-settings/providers/${provider.id}/toggle`, {}, {
        preserveScroll: true,
    });
}

function checkProvider(provider: Provider) {
    router.post(`/admin/ai-settings/providers/${provider.id}/check`, {}, {
        preserveScroll: true,
    });
}

function saveContext() {
    contextForm.put('/admin/ai-settings', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="AI Settings" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Bot class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-2xl font-semibold">AI Settings</h1>
                    <p class="text-sm text-muted-foreground">Providers, backend API keys, models, and assessment context</p>
                </div>
            </div>
            <Badge :variant="contextForm.enabled ? 'default' : 'secondary'">
                {{ contextForm.enabled ? 'Assessments Enabled' : 'Assessments Disabled' }}
            </Badge>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <div class="space-y-6">
                <section class="rounded-lg border bg-card p-5 shadow-sm">
                    <div class="mb-5 flex items-center gap-2">
                        <Power class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold">AI Providers</h2>
                    </div>

                    <div class="grid gap-3">
                        <button
                            v-for="provider in providers"
                            :key="provider.id"
                            type="button"
                            class="rounded-lg border p-4 text-left transition-colors hover:bg-muted/40"
                            :class="provider.id === selectedProvider.id ? 'border-primary bg-primary/5' : ''"
                            @click="activeProviderId = provider.id"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold">{{ provider.name }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ provider.selected_model }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <Badge :variant="statusVariant(provider.status)">
                                        {{ provider.status }}
                                    </Badge>
                                    <Badge :variant="provider.enabled ? 'default' : 'secondary'">
                                        {{ provider.enabled ? 'Enabled' : 'Disabled' }}
                                    </Badge>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <Badge :variant="provider.has_api_key ? 'outline' : 'secondary'">
                                    {{ provider.has_api_key ? 'Key saved' : 'No key' }}
                                </Badge>
                                <Badge :variant="provider.connected ? 'outline' : 'secondary'">
                                    {{ provider.connected ? 'Connected' : 'Future provider' }}
                                </Badge>
                            </div>
                        </button>
                    </div>
                </section>

                <section v-if="selectedProvider && selectedDraft" class="rounded-lg border bg-card p-5 shadow-sm">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <KeyRound class="h-4 w-4 text-muted-foreground" />
                            <h2 class="font-semibold">{{ selectedProvider.name }}</h2>
                        </div>
                        <Badge :variant="statusVariant(selectedProvider.status)">
                            {{ selectedProvider.status }}
                        </Badge>
                    </div>

                    <div class="space-y-5">
                        <div class="grid gap-1.5">
                            <Label for="selected_model">Selected Model</Label>
                            <Multiselect
                                id="selected_model"
                                v-model="selectedDraft.selected_model"
                                :options="selectedDraft.models"
                                :searchable="true"
                                :allow-empty="false"
                                :show-labels="false"
                                placeholder="Select a model"
                                class="ai-multiselect"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="models">Available Models</Label>
                            <Multiselect
                                id="models"
                                v-model="selectedDraft.models"
                                :options="selectedDraft.models"
                                :multiple="true"
                                :taggable="true"
                                :searchable="true"
                                :close-on-select="false"
                                :show-labels="false"
                                placeholder="Select or add models"
                                tag-placeholder="Add this model"
                                class="ai-multiselect"
                                @tag="addModel(selectedDraft, $event)"
                            />
                            <div class="flex gap-2">
                                <Input v-model="newModel" placeholder="Add a model name" class="flex-1" @keyup.enter="addCustomModel" />
                                <Button type="button" variant="outline" @click="addCustomModel">Add model</Button>
                            </div>
                            <p class="text-xs text-muted-foreground">Select existing models or add a custom model name.</p>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="api_key">Backend API Key</Label>
                            <Input id="api_key" v-model="selectedDraft.api_key" type="password" autocomplete="off" placeholder="Leave blank to keep current key" />
                        </div>

                        <label class="flex items-center gap-3 rounded-md border p-3">
                            <Checkbox v-model:checked="selectedDraft.clear_api_key" />
                            <span class="text-sm font-medium">Clear saved key</span>
                        </label>

                        <div v-if="selectedProvider.status_message" class="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                            {{ selectedProvider.status_message }}
                            <span v-if="selectedProvider.status_checked_at">Checked {{ selectedProvider.status_checked_at }}</span>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-3">
                            <Button type="button" variant="outline" @click="toggleProvider(selectedProvider)">
                                <Power class="mr-2 h-4 w-4" />
                                {{ selectedProvider.enabled ? 'Disable' : 'Enable' }}
                            </Button>
                            <Button type="button" variant="outline" @click="checkProvider(selectedProvider)">
                                <RotateCw class="mr-2 h-4 w-4" />
                                Check Status
                            </Button>
                            <Button type="button" @click="saveProvider(selectedProvider)">
                                <CheckCircle2 class="mr-2 h-4 w-4" />
                                Save Provider
                            </Button>
                        </div>
                    </div>
                </section>
            </div>

            <form @submit.prevent="saveContext" class="space-y-6">
                <section class="rounded-lg border bg-card p-5 shadow-sm">
                    <div class="mb-5 flex items-center gap-2">
                        <SlidersHorizontal class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold">Assessment Runtime</h2>
                    </div>

                    <div class="space-y-5">
                        <label class="flex items-center gap-3 rounded-md border p-3">
                            <Checkbox v-model:checked="contextForm.enabled" />
                            <span class="text-sm font-medium">Enable AI assessments</span>
                        </label>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label for="temperature">Temperature</Label>
                                <Input id="temperature" v-model="contextForm.temperature" type="number" min="0" max="1" step="0.01" />
                                <InputError :message="contextForm.errors.temperature" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="max_output_tokens">Max Output Tokens</Label>
                                <Input id="max_output_tokens" v-model="contextForm.max_output_tokens" type="number" min="256" max="8192" step="1" />
                                <InputError :message="contextForm.errors.max_output_tokens" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border bg-card p-5 shadow-sm">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <h2 class="font-semibold">Assessment Context</h2>
                        <label class="flex items-center gap-2 text-sm font-medium">
                            <Checkbox v-model:checked="contextForm.context_enabled" />
                            Context enabled
                        </label>
                    </div>

                    <div class="space-y-5">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-3 rounded-md border p-3">
                                <Checkbox v-model:checked="contextForm.include_assignment_instructions" />
                                <span class="text-sm font-medium">Include assignment instructions</span>
                            </label>
                            <label class="flex items-center gap-3 rounded-md border p-3">
                                <Checkbox v-model:checked="contextForm.include_rubric" />
                                <span class="text-sm font-medium">Include rubric</span>
                            </label>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="global_context">Global Context</Label>
                            <textarea id="global_context" v-model="contextForm.global_context" rows="5" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="School policies, grading tone, academic standards" />
                            <InputError :message="contextForm.errors.global_context" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="essay_context">Essay Context</Label>
                            <textarea id="essay_context" v-model="contextForm.essay_context" rows="5" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Essay-specific scoring preferences" />
                            <InputError :message="contextForm.errors.essay_context" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="code_context">Code Context</Label>
                            <textarea id="code_context" v-model="contextForm.code_context" rows="5" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Programming conventions, partial-credit rules" />
                            <InputError :message="contextForm.errors.code_context" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="plagiarism_context">Plagiarism Context</Label>
                            <textarea id="plagiarism_context" v-model="contextForm.plagiarism_context" rows="5" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Similarity thresholds and review standards" />
                            <InputError :message="contextForm.errors.plagiarism_context" />
                        </div>
                    </div>
                </section>

                <div class="flex justify-end border-t pt-5">
                    <Button type="submit" :disabled="contextForm.processing">
                        Save Runtime and Context
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>

<style>
.ai-multiselect .multiselect__tags {
    min-height: 2.25rem;
    border: 1px solid hsl(var(--input));
    border-radius: 0.375rem;
    background: transparent;
    padding: 0.45rem 2.5rem 0.45rem 0.75rem;
    font-size: 0.875rem;
}

.ai-multiselect .multiselect__single,
.ai-multiselect .multiselect__input,
.ai-multiselect .multiselect__placeholder {
    margin: 0;
    padding: 0;
    background: transparent;
    font-size: 0.875rem;
}

.ai-multiselect .multiselect__single {
    color: #ffffff !important;
}

.ai-multiselect .multiselect__input::placeholder,
.ai-multiselect .multiselect__placeholder {
    color: hsl(var(--muted-foreground));
}

.ai-multiselect .multiselect__tag {
    border-radius: 0.25rem;
    background: hsl(var(--primary));
    padding: 0.25rem 1.75rem 0.25rem 0.5rem;
}

.ai-multiselect .multiselect__option--highlight {
    background: hsl(var(--primary));
}

.ai-multiselect .multiselect__option--selected {
    background: hsl(var(--muted));
    color: hsl(var(--foreground));
}

.ai-multiselect .multiselect__content-wrapper {
    border-color: #d1d5db;
    background: #ffffff !important;
    z-index: 20;
}

.ai-multiselect .multiselect__content,
.ai-multiselect .multiselect__option {
    background: #ffffff !important;
    color: #1f2937 !important;
}

.ai-multiselect .multiselect__option--highlight {
    background: #e8f5bd !important;
    color: #1f2937 !important;
}

.ai-multiselect .multiselect__option--selected:not(.multiselect__option--highlight) {
    background: #f3f4f6 !important;
    color: #1f2937 !important;
}
</style>
