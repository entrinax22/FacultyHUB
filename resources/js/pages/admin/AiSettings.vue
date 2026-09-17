<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Bot,
    CheckCircle2,
    KeyRound,
    Power,
    RotateCw,
    SlidersHorizontal,
} from 'lucide-vue-next';
import {
    computed,
    onMounted,
    reactive,
    ref,
} from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import {
    showApiToast,
    showApiError,
} from '@/lib/flashToast';

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
    id: string;
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

type AiSettingsResponse = {
    success: boolean;
    message: string;
    data: {
        settings: Settings;
        providers: Provider[];
    };
};

type ApiResponse<T = any> = {
    success: boolean;
    message: string;
    data?: T;
    errors?: Record<string, string[]>;
};

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const loading = ref(false);
const savingContext = ref(false);
const savingProvider = ref(false);
const checkingProvider = ref(false);
const togglingProvider = ref(false);

const error = ref<string | null>(null);

const providers = ref<Provider[]>([]);

const activeProviderId = ref<string | undefined>(
    undefined,
);

const newModel = ref('');

/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
*/

const contextForm = reactive<Settings>({
    enabled: false,
    temperature: 0.7,
    max_output_tokens: 2048,
    include_assignment_instructions: true,
    include_rubric: true,
    context_enabled: true,
    global_context: '',
    essay_context: '',
    code_context: '',
    plagiarism_context: '',
});

/*
|--------------------------------------------------------------------------
| Provider Drafts
|--------------------------------------------------------------------------
*/

const providerDrafts = reactive<
    Record<string, ProviderDraft>
>({});

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin',
            },
            {
                title: 'AI Settings',
                href: '/admin/ai-settings',
            },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const selectedProvider = computed<
    Provider | undefined
>(() => {
    return (
        providers.value.find(
            (provider) =>
                provider.id ===
                activeProviderId.value,
        ) ?? providers.value[0]
    );
});

const selectedDraft = computed<
    ProviderDraft | undefined
>(() => {
    if (!selectedProvider.value) {
        return undefined;
    }

    return providerDrafts[
        selectedProvider.value.id
    ];
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function createProviderDraft(
    provider: Provider,
): ProviderDraft {
    return {
        api_key: '',
        clear_api_key: false,
        models: [...provider.models],
        selected_model:
            provider.selected_model,
    };
}

function syncProviderDrafts() {
    for (const provider of providers.value) {
        const existingDraft =
            providerDrafts[provider.id];

        if (!existingDraft) {
            providerDrafts[provider.id] =
                createProviderDraft(provider);

            continue;
        }

        existingDraft.models = [
            ...provider.models,
        ];

        existingDraft.selected_model =
            provider.selected_model;
    }
}

function applySettings(data: Settings) {
    Object.assign(contextForm, data);
}

function applyProviders(data: Provider[]) {
    providers.value = data;

    syncProviderDrafts();

    /*
     * Keep the currently selected provider if it
     * still exists.
     *
     * Otherwise select the enabled provider first,
     * followed by the first provider.
     */
    if (
        !activeProviderId.value ||
        !providers.value.some(
            (provider) =>
                provider.id ===
                activeProviderId.value,
        )
    ) {
        activeProviderId.value =
            providers.value.find(
                (provider) =>
                    provider.enabled,
            )?.id ??
            providers.value[0]?.id;
    }
}

function statusVariant(
    status: Provider['status'],
) {
    if (status === 'active') {
        return 'default';
    }

    if (status === 'exhausted') {
        return 'destructive';
    }

    return 'secondary';
}

/*
|--------------------------------------------------------------------------
| Load AI Settings
|--------------------------------------------------------------------------
*/

async function loadSettings() {
    loading.value = true;
    error.value = null;

    try {
        const response =
            await axios.get<AiSettingsResponse>(
                '/admin/ai-settings/data',
            );

        if (!response.data.success) {
            error.value =
                response.data.message ||
                'Failed to load AI settings.';

            return;
        }

        applySettings(
            response.data.data.settings,
        );

        applyProviders(
            response.data.data.providers,
        );
    } catch (err: any) {
        console.error(
            'AI SETTINGS ERROR:',
            err,
        );

        error.value =
            err.response?.data?.message ||
            'Failed to load AI settings.';

        showApiError(err);
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Provider
|--------------------------------------------------------------------------
*/

async function saveProvider(
    provider: Provider,
) {
    const draft =
        providerDrafts[provider.id];

    if (!draft) {
        return;
    }

    savingProvider.value = true;

    try {
        const response =
            await axios.put<ApiResponse>(
                `/admin/ai-settings/providers/${provider.id}`,
                {
                    api_key: draft.api_key,
                    clear_api_key:
                        draft.clear_api_key,
                    models:
                        draft.models.join('\n'),
                    selected_model:
                        draft.selected_model,
                },
            );

        showApiToast(response);

        if (response.data.success) {
            /*
             * Never keep the API key in the
             * frontend state after saving.
             */
            draft.api_key = '';
            draft.clear_api_key = false;

            /*
             * Reload provider data so the
             * encrypted ID and server state
             * remain authoritative.
             */
            await loadSettings();
        }
    } catch (err: any) {
        console.error(
            'SAVE PROVIDER ERROR:',
            err,
        );

        showApiError(err);
    } finally {
        savingProvider.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Models
|--------------------------------------------------------------------------
*/

function addModel(
    provider: ProviderDraft,
    model: string,
) {
    const normalizedModel =
        model.trim();

    if (
        !normalizedModel ||
        provider.models.includes(
            normalizedModel,
        )
    ) {
        return;
    }

    provider.models.push(
        normalizedModel,
    );

    provider.selected_model =
        normalizedModel;
}

function addCustomModel() {
    if (!selectedDraft.value) {
        return;
    }

    addModel(
        selectedDraft.value,
        newModel.value,
    );

    newModel.value = '';
}

/*
|--------------------------------------------------------------------------
| Toggle Provider
|--------------------------------------------------------------------------
*/

async function toggleProvider(
    provider: Provider,
) {
    togglingProvider.value = true;

    try {
        const response =
            await axios.put<ApiResponse>(
                `/admin/ai-settings/providers/${provider.id}/toggle`,
            );

        showApiToast(response);

        if (response.data.success) {
            await loadSettings();
        }
    } catch (err: any) {
        console.error(
            'TOGGLE PROVIDER ERROR:',
            err,
        );

        showApiError(err);
    } finally {
        togglingProvider.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Check Provider
|--------------------------------------------------------------------------
*/

async function checkProvider(
    provider: Provider,
) {
    checkingProvider.value = true;

    try {
        const response =
            await axios.post<ApiResponse>(
                `/admin/ai-settings/providers/${provider.id}/check`,
            );

        showApiToast(response);

        /*
         * The status check can return a successful
         * API response even when the provider itself
         * is inactive/exhausted.
         *
         * Reload the provider state regardless of
         * the returned provider status.
         */
        if (response.data.success) {
            await loadSettings();
        }
    } catch (err: any) {
        console.error(
            'CHECK PROVIDER ERROR:',
            err,
        );

        showApiError(err);
    } finally {
        checkingProvider.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Save Runtime / Context
|--------------------------------------------------------------------------
*/

async function saveContext() {
    savingContext.value = true;

    try {
        const response =
            await axios.put<ApiResponse<{
                settings: Settings;
            }>>(
                '/admin/ai-settings',
                {
                    enabled:
                        contextForm.enabled,

                    temperature:
                        contextForm.temperature,

                    max_output_tokens:
                        contextForm.max_output_tokens,

                    include_assignment_instructions:
                        contextForm
                            .include_assignment_instructions,

                    include_rubric:
                        contextForm
                            .include_rubric,

                    context_enabled:
                        contextForm
                            .context_enabled,

                    global_context:
                        contextForm
                            .global_context,

                    essay_context:
                        contextForm
                            .essay_context,

                    code_context:
                        contextForm
                            .code_context,

                    plagiarism_context:
                        contextForm
                            .plagiarism_context,
                },
            );

        showApiToast(response);

        if (
            response.data.success &&
            response.data.data?.settings
        ) {
            applySettings(
                response.data.data.settings,
            );
        }
    } catch (err: any) {
        console.error(
            'SAVE AI SETTINGS ERROR:',
            err,
        );

        showApiError(err);
    } finally {
        savingContext.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Initial Load
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadSettings();
});
</script>

<template>
    <Head title="AI Settings" />

    <div
        class="flex min-h-full flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-4 lg:p-6"
    >
        <!-- Loading -->
        <div
            v-if="loading"
            class="flex min-h-[400px] items-center justify-center"
        >
            <div
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <RotateCw
                    class="h-4 w-4 animate-spin"
                />

                Loading AI settings...
            </div>
        </div>

        <!-- Error -->
        <div
            v-else-if="error"
            class="rounded-lg border border-destructive/30 bg-destructive/5 p-5"
        >
            <h2
                class="font-semibold text-destructive"
            >
                Unable to load AI settings
            </h2>

            <p
                class="mt-1 text-sm text-muted-foreground"
            >
                {{ error }}
            </p>

            <Button
                type="button"
                variant="outline"
                class="mt-4"
                @click="loadSettings"
            >
                <RotateCw class="mr-2 h-4 w-4" />
                Try Again
            </Button>
        </div>

        <!-- Main Content -->
        <template v-else>
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div
                    class="flex min-w-0 items-start gap-3"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 sm:h-10 sm:w-10"
                    >
                        <Bot
                            class="h-4 w-4 text-primary sm:h-5 sm:w-5"
                        />
                    </div>

                    <div class="min-w-0">
                        <h1
                            class="text-xl font-semibold sm:text-2xl"
                        >
                            AI Settings
                        </h1>

                        <p
                            class="mt-1 max-w-2xl text-xs text-muted-foreground sm:text-sm"
                        >
                            Providers, backend API keys,
                            models, and assessment
                            context
                        </p>
                    </div>
                </div>

                <Badge
                    :variant="
                        contextForm.enabled
                            ? 'default'
                            : 'secondary'
                    "
                    class="w-fit shrink-0"
                >
                    {{
                        contextForm.enabled
                            ? 'Assessments Enabled'
                            : 'Assessments Disabled'
                    }}
                </Badge>
            </div>

            <!-- Main Layout -->
            <div
                class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] xl:gap-6"
            >
                <!-- LEFT COLUMN -->
                <div
                    class="min-w-0 space-y-5 sm:space-y-6"
                >
                    <!-- Providers -->
                    <section
                        class="rounded-lg border bg-card p-4 shadow-sm sm:p-5"
                    >
                        <div
                            class="mb-4 flex items-center gap-2 sm:mb-5"
                        >
                            <Power
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />

                            <h2
                                class="font-semibold"
                            >
                                AI Providers
                            </h2>
                        </div>

                        <div
                            v-if="providers.length"
                            class="grid gap-3"
                        >
                            <button
                                v-for="provider in providers"
                                :key="provider.id"
                                type="button"
                                class="min-w-0 rounded-lg border p-3 text-left transition-colors hover:bg-muted/40 sm:p-4"
                                :class="
                                    provider.id ===
                                    selectedProvider?.id
                                        ? 'border-primary bg-primary/5'
                                        : ''
                                "
                                @click="
                                    activeProviderId =
                                        provider.id
                                "
                            >
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div
                                        class="min-w-0"
                                    >
                                        <p
                                            class="truncate font-semibold"
                                        >
                                            {{
                                                provider.name
                                            }}
                                        </p>

                                        <p
                                            class="mt-1 truncate text-xs text-muted-foreground"
                                        >
                                            {{
                                                provider.selected_model
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        class="flex flex-wrap items-center gap-1.5 sm:shrink-0 sm:justify-end"
                                    >
                                        <Badge
                                            :variant="
                                                statusVariant(
                                                    provider.status,
                                                )
                                            "
                                            class="text-xs"
                                        >
                                            {{
                                                provider.status
                                            }}
                                        </Badge>

                                        <Badge
                                            :variant="
                                                provider.enabled
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                            class="text-xs"
                                        >
                                            {{
                                                provider.enabled
                                                    ? 'Enabled'
                                                    : 'Disabled'
                                            }}
                                        </Badge>
                                    </div>
                                </div>

                                <div
                                    class="mt-3 flex flex-wrap gap-1.5"
                                >
                                    <Badge
                                        :variant="
                                            provider.has_api_key
                                                ? 'outline'
                                                : 'secondary'
                                        "
                                        class="text-xs"
                                    >
                                        {{
                                            provider.has_api_key
                                                ? 'Key saved'
                                                : 'No key'
                                        }}
                                    </Badge>

                                    <Badge
                                        :variant="
                                            provider.connected
                                                ? 'outline'
                                                : 'secondary'
                                        "
                                        class="text-xs"
                                    >
                                        {{
                                            provider.connected
                                                ? 'Connected'
                                                : 'Future provider'
                                        }}
                                    </Badge>
                                </div>
                            </button>
                        </div>

                        <div
                            v-else
                            class="rounded-md border border-dashed p-5 text-center text-sm text-muted-foreground"
                        >
                            No AI providers are
                            configured.
                        </div>
                    </section>

                    <!-- Selected Provider -->
                    <section
                        v-if="
                            selectedProvider &&
                            selectedDraft
                        "
                        class="rounded-lg border bg-card p-4 shadow-sm sm:p-5"
                    >
                        <div
                            class="mb-4 flex flex-col gap-2 sm:mb-5 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div
                                class="flex items-center gap-2"
                            >
                                <KeyRound
                                    class="h-4 w-4 shrink-0 text-muted-foreground"
                                />

                                <h2
                                    class="font-semibold"
                                >
                                    {{
                                        selectedProvider.name
                                    }}
                                </h2>
                            </div>

                            <Badge
                                :variant="
                                    statusVariant(
                                        selectedProvider.status,
                                    )
                                "
                                class="w-fit"
                            >
                                {{
                                    selectedProvider.status
                                }}
                            </Badge>
                        </div>

                        <div
                            class="space-y-4 sm:space-y-5"
                        >
                            <!-- Selected Model -->
                            <div
                                class="grid gap-1.5"
                            >
                                <Label
                                    for="selected_model"
                                >
                                    Selected Model
                                </Label>

                                <Multiselect
                                    id="selected_model"
                                    v-model="
                                        selectedDraft.selected_model
                                    "
                                    :options="
                                        selectedDraft.models
                                    "
                                    :searchable="true"
                                    :allow-empty="false"
                                    :show-labels="false"
                                    placeholder="Select a model"
                                    class="ai-multiselect"
                                />
                            </div>

                            <!-- Available Models -->
                            <div
                                class="grid gap-1.5"
                            >
                                <Label
                                    for="models"
                                >
                                    Available Models
                                </Label>

                                <Multiselect
                                    id="models"
                                    v-model="
                                        selectedDraft.models
                                    "
                                    :options="
                                        selectedDraft.models
                                    "
                                    :multiple="true"
                                    :taggable="true"
                                    :searchable="true"
                                    :close-on-select="false"
                                    :show-labels="false"
                                    placeholder="Select or add models"
                                    tag-placeholder="Add this model"
                                    class="ai-multiselect"
                                    @tag="
                                        addModel(
                                            selectedDraft,
                                            $event,
                                        )
                                    "
                                />

                                <div
                                    class="flex flex-col gap-2 sm:flex-row"
                                >
                                    <Input
                                        v-model="
                                            newModel
                                        "
                                        placeholder="Add a model name"
                                        class="min-w-0 flex-1"
                                        @keyup.enter="
                                            addCustomModel
                                        "
                                    />

                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="w-full sm:w-auto"
                                        @click="
                                            addCustomModel
                                        "
                                    >
                                        Add model
                                    </Button>
                                </div>

                                <p
                                    class="text-xs text-muted-foreground"
                                >
                                    Select existing
                                    models or add a
                                    custom model name.
                                </p>
                            </div>

                            <!-- API Key -->
                            <div
                                class="grid gap-1.5"
                            >
                                <Label
                                    for="api_key"
                                >
                                    Backend API Key
                                </Label>

                                <Input
                                    id="api_key"
                                    v-model="
                                        selectedDraft.api_key
                                    "
                                    type="password"
                                    autocomplete="off"
                                    placeholder="Leave blank to keep current key"
                                />
                            </div>

                            <!-- Clear API Key -->
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-md border p-3"
                            >
                                <Checkbox
                                    v-model:checked="
                                        selectedDraft.clear_api_key
                                    "
                                />

                                <span
                                    class="text-sm font-medium"
                                >
                                    Clear saved key
                                </span>
                            </label>

                            <!-- Status Message -->
                            <div
                                v-if="
                                    selectedProvider.status_message
                                "
                                class="rounded-md border bg-muted/30 p-3 text-xs text-muted-foreground sm:text-sm"
                            >
                                <p>
                                    {{
                                        selectedProvider.status_message
                                    }}
                                </p>

                                <span
                                    v-if="
                                        selectedProvider.status_checked_at
                                    "
                                    class="mt-1 block"
                                >
                                    Checked
                                    {{
                                        selectedProvider.status_checked_at
                                    }}
                                </span>
                            </div>

                            <!-- Provider Actions -->
                            <div
                                class="grid gap-2 sm:grid-cols-3"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full"
                                    :disabled="
                                        togglingProvider
                                    "
                                    @click="
                                        toggleProvider(
                                            selectedProvider,
                                        )
                                    "
                                >
                                    <Power
                                        class="mr-2 h-4 w-4"
                                    />

                                    {{
                                        togglingProvider
                                            ? 'Updating...'
                                            : selectedProvider.enabled
                                                ? 'Disable'
                                                : 'Enable'
                                    }}
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full"
                                    :disabled="
                                        checkingProvider
                                    "
                                    @click="
                                        checkProvider(
                                            selectedProvider,
                                        )
                                    "
                                >
                                    <RotateCw
                                        class="mr-2 h-4 w-4"
                                        :class="
                                            checkingProvider
                                                ? 'animate-spin'
                                                : ''
                                        "
                                    />

                                    {{
                                        checkingProvider
                                            ? 'Checking...'
                                            : 'Check Status'
                                    }}
                                </Button>

                                <Button
                                    type="button"
                                    class="w-full"
                                    :disabled="
                                        savingProvider
                                    "
                                    @click="
                                        saveProvider(
                                            selectedProvider,
                                        )
                                    "
                                >
                                    <CheckCircle2
                                        class="mr-2 h-4 w-4"
                                    />

                                    {{
                                        savingProvider
                                            ? 'Saving...'
                                            : 'Save Provider'
                                    }}
                                </Button>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- RIGHT COLUMN -->
                <form
                    class="min-w-0 space-y-5 sm:space-y-6"
                    @submit.prevent="saveContext"
                >
                    <!-- Runtime -->
                    <section
                        class="rounded-lg border bg-card p-4 shadow-sm sm:p-5"
                    >
                        <div
                            class="mb-4 flex items-center gap-2 sm:mb-5"
                        >
                            <SlidersHorizontal
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />

                            <h2
                                class="font-semibold"
                            >
                                Assessment Runtime
                            </h2>
                        </div>

                        <div
                            class="space-y-4 sm:space-y-5"
                        >
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-md border p-3"
                            >
                                <Checkbox
                                    v-model:checked="
                                        contextForm.enabled
                                    "
                                />

                                <span
                                    class="text-sm font-medium"
                                >
                                    Enable AI assessments
                                </span>
                            </label>

                            <div
                                class="grid gap-4 sm:grid-cols-2"
                            >
                                <div
                                    class="grid gap-1.5"
                                >
                                    <Label
                                        for="temperature"
                                    >
                                        Temperature
                                    </Label>

                                    <Input
                                        id="temperature"
                                        v-model.number="
                                            contextForm.temperature
                                        "
                                        type="number"
                                        min="0"
                                        max="1"
                                        step="0.01"
                                    />
                                </div>

                                <div
                                    class="grid gap-1.5"
                                >
                                    <Label
                                        for="max_output_tokens"
                                    >
                                        Max Output Tokens
                                    </Label>

                                    <Input
                                        id="max_output_tokens"
                                        v-model.number="
                                            contextForm.max_output_tokens
                                        "
                                        type="number"
                                        min="256"
                                        max="8192"
                                        step="1"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Assessment Context -->
                    <section
                        class="rounded-lg border bg-card p-4 shadow-sm sm:p-5"
                    >
                        <div
                            class="mb-4 flex flex-col gap-3 sm:mb-5 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <h2
                                class="font-semibold"
                            >
                                Assessment Context
                            </h2>

                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm font-medium"
                            >
                                <Checkbox
                                    v-model:checked="
                                        contextForm.context_enabled
                                    "
                                />

                                Context enabled
                            </label>
                        </div>

                        <div
                            class="space-y-4 sm:space-y-5"
                        >
                            <!-- Context Options -->
                            <div
                                class="grid gap-2 sm:grid-cols-2 sm:gap-3"
                            >
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-md border p-3"
                                >
                                    <Checkbox
                                        v-model:checked="
                                            contextForm.include_assignment_instructions
                                        "
                                    />

                                    <span
                                        class="text-sm font-medium"
                                    >
                                        Include assignment
                                        instructions
                                    </span>
                                </label>

                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-md border p-3"
                                >
                                    <Checkbox
                                        v-model:checked="
                                            contextForm.include_rubric
                                        "
                                    />

                                    <span
                                        class="text-sm font-medium"
                                    >
                                        Include rubric
                                    </span>
                                </label>
                            </div>

                            <!-- Global Context -->
                            <div
                                class="grid gap-1.5"
                            >
                                <Label
                                    for="global_context"
                                >
                                    Global Context
                                </Label>

                                <textarea
                                    id="global_context"
                                    v-model="
                                        contextForm.global_context
                                    "
                                    rows="5"
                                    class="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="School policies, grading tone, academic standards"
                                ></textarea>
                            </div>

                            <!-- Essay Context -->
                            <div
                                class="grid gap-1.5"
                            >
                                <Label
                                    for="essay_context"
                                >
                                    Essay Context
                                </Label>

                                <textarea
                                    id="essay_context"
                                    v-model="
                                        contextForm.essay_context
                                    "
                                    rows="5"
                                    class="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="Essay-specific scoring preferences"
                                ></textarea>
                            </div>

                            <!-- Code Context -->
                            <div
                                class="grid gap-1.5"
                            >
                                <Label
                                    for="code_context"
                                >
                                    Code Context
                                </Label>

                                <textarea
                                    id="code_context"
                                    v-model="
                                        contextForm.code_context
                                    "
                                    rows="5"
                                    class="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="Programming conventions, partial-credit rules"
                                ></textarea>
                            </div>

                            <!-- Plagiarism Context -->
                            <div
                                class="grid gap-1.5"
                            >
                                <Label
                                    for="plagiarism_context"
                                >
                                    Plagiarism Context
                                </Label>

                                <textarea
                                    id="plagiarism_context"
                                    v-model="
                                        contextForm.plagiarism_context
                                    "
                                    rows="5"
                                    class="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="Similarity thresholds and review standards"
                                ></textarea>
                            </div>
                        </div>
                    </section>

                    <!-- Save -->
                    <div
                        class="flex border-t pt-4 sm:justify-end sm:pt-5"
                    >
                        <Button
                            type="submit"
                            :disabled="
                                savingContext
                            "
                            class="w-full sm:w-auto"
                        >
                            {{
                                savingContext
                                    ? 'Saving...'
                                    : 'Save Runtime and Context'
                            }}
                        </Button>
                    </div>
                </form>
            </div>
        </template>
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
    z-index: 20;
    border-color: #d1d5db;
    background: #ffffff !important;
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

.ai-multiselect
    .multiselect__option--selected:not(
        .multiselect__option--highlight
    ) {
    background: #f3f4f6 !important;
    color: #1f2937 !important;
}

/* Prevent the multiselect from becoming too wide on small screens */
.ai-multiselect {
    min-width: 0;
}

.ai-multiselect .multiselect__tags-wrap {
    max-width: 100%;
}

.ai-multiselect .multiselect__tag {
    max-width: calc(100% - 0.5rem);
}

.ai-multiselect .multiselect__tag span {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
}
</style>