<script setup lang="ts">
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Redo2,
    Underline as UnderlineIcon,
    Undo2,
} from 'lucide-vue-next';
import { watch, watchEffect, useAttrs, onBeforeUnmount } from 'vue';
import type { HTMLAttributes } from 'vue';
import { sanitizeRichText } from '@/lib/richText';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
        disabled?: boolean;
        minHeight?: string;
        maxHeight?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        modelValue: '',
        placeholder: 'Start writing...',
        disabled: false,
        minHeight: '9rem',
        maxHeight: '24rem',
        class: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = useEditor({
    content: sanitizeRichText(props.modelValue),
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            link: false,
            underline: false,
        }),
        Link.configure({
            autolink: false,
            linkOnPaste: false,
            openOnClick: false,
        }),
        Underline,
    ],
    editorProps: {
        attributes: (state) => ({
            class: 'rich-text-prose focus:outline-none',
            spellcheck: 'true',
            'data-placeholder': props.placeholder,
            'data-empty': String(
                state.doc.childCount === 1 &&
                    state.doc.firstChild?.content.size === 0,
            ),
        }),
    },
    onUpdate: ({ editor: currentEditor }) => {
        emit('update:modelValue', currentEditor.getHTML());
    },
});

watchEffect(() => {
    const forwarded = Object.fromEntries(
        Object.entries(attrs)
            .filter(
                ([name, value]) =>
                    (name === 'id' || name.startsWith('aria-')) &&
                    value !== undefined &&
                    value !== null,
            )
            .map(([name, value]) => [name, String(value)]),
    );

    if (attrs.id && !attrs['aria-labelledby'] && !attrs['aria-label']) {
        forwarded['aria-labelledby'] = `${attrs.id}-label`;
    }

    forwarded['aria-multiline'] = 'true';
    forwarded['aria-required'] = String(
        attrs.required !== undefined && attrs.required !== false,
    );

    editor.value?.setOptions({
        editorProps: {
            attributes: (state) => ({
                ...forwarded,
                class: 'rich-text-prose focus:outline-none',
                spellcheck: 'true',
                'data-placeholder': props.placeholder,
                'data-empty': String(
                    state.doc.childCount === 1 &&
                        state.doc.firstChild?.content.size === 0,
                ),
            }),
        },
    });
});

watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && editor.value.getHTML() !== value) {
            editor.value.commands.setContent(sanitizeRichText(value), {
                emitUpdate: false,
            });
        }
    },
);

watch(
    () => props.disabled,
    (disabled) => {
        editor.value?.setEditable(!disabled);
    },
);

const setLink = (): void => {
    if (!editor.value) {
        return;
    }

    const currentHref = editor.value.getAttributes('link').href ?? '';
    const href = window.prompt(
        'Enter a safe link (https:// or mailto:)',
        currentHref,
    );

    if (href === null) {
        return;
    }

    const trimmedHref = href.trim();

    if (trimmedHref === '') {
        editor.value.chain().focus().unsetLink().run();

        return;
    }

    if (!/^(https?:|mailto:)/i.test(trimmedHref)) {
        return;
    }

    editor.value.chain().focus().setLink({ href: trimmedHref }).run();
};

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
    <div
        :class="
            cn(
                'overflow-hidden rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30',
                props.class,
            )
        "
    >
        <div
            class="flex flex-wrap items-center gap-1 border-b border-border/70 bg-muted/30 p-1.5 dark:bg-muted/10"
            role="toolbar"
            aria-label="Text formatting"
        >
            <button
                v-for="action in [
                    {
                        label: 'Bold',
                        icon: Bold,
                        run: () => editor?.chain().focus().toggleBold().run(),
                        active: 'bold',
                    },
                    {
                        label: 'Italic',
                        icon: Italic,
                        run: () => editor?.chain().focus().toggleItalic().run(),
                        active: 'italic',
                    },
                    {
                        label: 'Underline',
                        icon: UnderlineIcon,
                        run: () =>
                            editor?.chain().focus().toggleUnderline().run(),
                        active: 'underline',
                    },
                    {
                        label: 'Heading 2',
                        icon: Heading2,
                        run: () =>
                            editor
                                ?.chain()
                                .focus()
                                .toggleHeading({ level: 2 })
                                .run(),
                        active: 'heading',
                        attrs: { level: 2 },
                    },
                    {
                        label: 'Heading 3',
                        icon: Heading3,
                        run: () =>
                            editor
                                ?.chain()
                                .focus()
                                .toggleHeading({ level: 3 })
                                .run(),
                        active: 'heading',
                        attrs: { level: 3 },
                    },
                    {
                        label: 'Bulleted list',
                        icon: List,
                        run: () =>
                            editor?.chain().focus().toggleBulletList().run(),
                        active: 'bulletList',
                    },
                    {
                        label: 'Numbered list',
                        icon: ListOrdered,
                        run: () =>
                            editor?.chain().focus().toggleOrderedList().run(),
                        active: 'orderedList',
                    },
                ]"
                :key="action.label"
                type="button"
                :disabled="props.disabled || !editor"
                :aria-label="action.label"
                :aria-pressed="
                    editor?.isActive(action.active, action.attrs) ?? false
                "
                :title="action.label"
                class="inline-flex size-8 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                @click="action.run"
            >
                <component
                    :is="action.icon"
                    class="size-4"
                    aria-hidden="true"
                />
            </button>

            <button
                type="button"
                :disabled="props.disabled || !editor"
                aria-label="Insert link"
                title="Insert link"
                class="inline-flex size-8 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                @click="setLink"
            >
                <LinkIcon class="size-4" aria-hidden="true" />
            </button>

            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />

            <button
                type="button"
                :disabled="props.disabled || !editor?.can().undo()"
                aria-label="Undo"
                title="Undo"
                class="inline-flex size-8 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                @click="editor?.chain().focus().undo().run()"
            >
                <Undo2 class="size-4" aria-hidden="true" />
            </button>

            <button
                type="button"
                :disabled="props.disabled || !editor?.can().redo()"
                aria-label="Redo"
                title="Redo"
                class="inline-flex size-8 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                @click="editor?.chain().focus().redo().run()"
            >
                <Redo2 class="size-4" aria-hidden="true" />
            </button>
        </div>

        <EditorContent
            :editor="editor"
            :style="{ minHeight: props.minHeight, maxHeight: props.maxHeight }"
            :class="[
                'overflow-y-auto px-3 py-2 text-base md:text-sm',
                'text-foreground placeholder:text-muted-foreground',
                'disabled:pointer-events-none disabled:opacity-50',
            ]"
        />
    </div>
</template>

<style scoped>
:deep(.ProseMirror) {
    min-height: inherit;
    outline: none;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

:deep(.ProseMirror[data-empty='true']::before) {
    color: var(--muted-foreground);
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}

:deep(.ProseMirror h2) {
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.5;
}

:deep(.ProseMirror h3) {
    font-size: 1.125rem;
    font-weight: 600;
    line-height: 1.5;
}

:deep(.ProseMirror ul) {
    list-style: disc;
    padding-left: 1.5rem;
}

:deep(.ProseMirror ol) {
    list-style: decimal;
    padding-left: 1.5rem;
}

:deep(.ProseMirror a) {
    color: var(--primary);
    text-decoration: underline;
}
</style>
