<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent, Node, mergeAttributes } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Youtube from '@tiptap/extension-youtube';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import axios from 'axios';
import {
    Bold, Italic, Underline as UnderlineIcon, Strikethrough,
    List, ListOrdered, Quote, Minus,
    AlignLeft, AlignCenter, AlignRight,
    Link2, ImageIcon, Video, Youtube as YoutubeIcon,
    Heading1, Heading2, Heading3, Undo, Redo,
    Loader2,
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

// ── Extensão customizada para <video> HTML5 ─────────────────────
const VideoNode = Node.create({
    name: 'video',
    group: 'block',
    atom: true,
    addAttributes() {
        return { src: { default: null }, controls: { default: true } };
    },
    parseHTML() {
        return [{ tag: 'video' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['video', mergeAttributes(HTMLAttributes, { controls: true, class: 'w-full rounded-lg my-3 max-h-[480px]' })];
    },
});

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Link.configure({ openOnClick: false, HTMLAttributes: { class: 'text-blue-400 underline' } }),
        Image.configure({ HTMLAttributes: { class: 'max-w-full rounded-lg my-3' } }),
        Youtube.configure({ width: '100%', height: 360, HTMLAttributes: { class: 'w-full rounded-xl my-3' } }),
        VideoNode,
    ],
    onUpdate({ editor }) {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val || '', false);
    }
});
onBeforeUnmount(() => editor.value?.destroy());

// ── Upload ──────────────────────────────────────────────────────
const uploading = ref(false);
const imageInput = ref(null);
const videoInput = ref(null);

async function uploadFile(file, type) {
    uploading.value = true;
    try {
        const fd = new FormData();
        fd.append('file', file);
        const { data } = await axios.post('/member-posts/upload-media', fd);
        if (type === 'image') {
            editor.value.chain().focus().setImage({ src: data.url }).run();
        } else {
            editor.value.chain().focus().insertContent({
                type: 'video',
                attrs: { src: data.url },
            }).run();
        }
    } catch {
        alert('Erro ao fazer upload do arquivo.');
    } finally {
        uploading.value = false;
    }
}

function onImagePicked(e) {
    const file = e.target.files[0];
    if (file) uploadFile(file, 'image');
    e.target.value = '';
}

function onVideoPicked(e) {
    const file = e.target.files[0];
    if (file) uploadFile(file, 'video');
    e.target.value = '';
}

function addYoutube() {
    const url = window.prompt('URL do YouTube ou Vimeo:');
    if (url) editor.value.chain().focus().setYoutubeVideo({ src: url }).run();
}

function addLink() {
    const url = window.prompt('URL do link:');
    if (url) editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-800">

        <!-- Inputs ocultos -->
        <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="onImagePicked" />
        <input ref="videoInput" type="file" accept="video/mp4,video/webm,video/mov" class="hidden" @change="onVideoPicked" />

        <!-- Toolbar -->
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b border-zinc-200 bg-zinc-50 px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-900">

            <button type="button" title="Desfazer" :disabled="!editor.can().undo()" class="toolbar-btn" @click="editor.chain().focus().undo().run()"><Undo class="h-4 w-4" /></button>
            <button type="button" title="Refazer" :disabled="!editor.can().redo()" class="toolbar-btn" @click="editor.chain().focus().redo().run()"><Redo class="h-4 w-4" /></button>

            <span class="sep" />

            <button type="button" :class="['toolbar-btn', editor.isActive('heading', { level: 1 }) && 'active']" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"><Heading1 class="h-4 w-4" /></button>
            <button type="button" :class="['toolbar-btn', editor.isActive('heading', { level: 2 }) && 'active']" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"><Heading2 class="h-4 w-4" /></button>
            <button type="button" :class="['toolbar-btn', editor.isActive('heading', { level: 3 }) && 'active']" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"><Heading3 class="h-4 w-4" /></button>

            <span class="sep" />

            <button type="button" :class="['toolbar-btn', editor.isActive('bold') && 'active']"      @click="editor.chain().focus().toggleBold().run()"><Bold class="h-4 w-4" /></button>
            <button type="button" :class="['toolbar-btn', editor.isActive('italic') && 'active']"    @click="editor.chain().focus().toggleItalic().run()"><Italic class="h-4 w-4" /></button>
            <button type="button" :class="['toolbar-btn', editor.isActive('underline') && 'active']" @click="editor.chain().focus().toggleUnderline().run()"><UnderlineIcon class="h-4 w-4" /></button>
            <button type="button" :class="['toolbar-btn', editor.isActive('strike') && 'active']"    @click="editor.chain().focus().toggleStrike().run()"><Strikethrough class="h-4 w-4" /></button>

            <span class="sep" />

            <button type="button" :class="['toolbar-btn', editor.isActive({ textAlign: 'left' })   && 'active']" @click="editor.chain().focus().setTextAlign('left').run()"><AlignLeft class="h-4 w-4" /></button>
            <button type="button" :class="['toolbar-btn', editor.isActive({ textAlign: 'center' }) && 'active']" @click="editor.chain().focus().setTextAlign('center').run()"><AlignCenter class="h-4 w-4" /></button>
            <button type="button" :class="['toolbar-btn', editor.isActive({ textAlign: 'right' })  && 'active']" @click="editor.chain().focus().setTextAlign('right').run()"><AlignRight class="h-4 w-4" /></button>

            <span class="sep" />

            <button type="button" :class="['toolbar-btn', editor.isActive('bulletList') && 'active']"  @click="editor.chain().focus().toggleBulletList().run()"><List class="h-4 w-4" /></button>
            <button type="button" :class="['toolbar-btn', editor.isActive('orderedList') && 'active']" @click="editor.chain().focus().toggleOrderedList().run()"><ListOrdered class="h-4 w-4" /></button>
            <button type="button" :class="['toolbar-btn', editor.isActive('blockquote') && 'active']"  @click="editor.chain().focus().toggleBlockquote().run()"><Quote class="h-4 w-4" /></button>
            <button type="button" class="toolbar-btn" @click="editor.chain().focus().setHorizontalRule().run()"><Minus class="h-4 w-4" /></button>

            <span class="sep" />

            <button type="button" :class="['toolbar-btn', editor.isActive('link') && 'active']" title="Link" @click="addLink"><Link2 class="h-4 w-4" /></button>

            <!-- Upload imagem -->
            <button
                type="button"
                class="toolbar-btn"
                title="Inserir imagem"
                :disabled="uploading"
                @click="imageInput.click()"
            >
                <Loader2 v-if="uploading" class="h-4 w-4 animate-spin" />
                <ImageIcon v-else class="h-4 w-4" />
            </button>

            <!-- Upload vídeo -->
            <button
                type="button"
                class="toolbar-btn"
                title="Inserir vídeo (MP4/WebM)"
                :disabled="uploading"
                @click="videoInput.click()"
            >
                <Video class="h-4 w-4" />
            </button>

            <!-- YouTube embed -->
            <button type="button" class="toolbar-btn" title="Embed YouTube/Vimeo" @click="addYoutube">
                <YoutubeIcon class="h-4 w-4" />
            </button>
        </div>

        <!-- Área de edição -->
        <EditorContent :editor="editor" class="rich-editor-content" />
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.toolbar-btn {
    @apply flex h-7 w-7 items-center justify-center rounded p-1 text-zinc-600 transition
           hover:bg-zinc-200 dark:text-zinc-300 dark:hover:bg-zinc-700
           disabled:opacity-30 disabled:cursor-not-allowed;
}
.toolbar-btn.active {
    @apply bg-zinc-200 text-zinc-900 dark:bg-zinc-600 dark:text-white;
}
.sep {
    @apply mx-1 block h-5 w-px bg-zinc-300 dark:bg-zinc-600;
}

.rich-editor-content {
    @apply min-h-[240px] px-4 py-3;
}

:deep(.ProseMirror) {
    @apply outline-none min-h-[200px] text-sm text-zinc-900 dark:text-zinc-100;
}
:deep(.ProseMirror h1) { @apply text-2xl font-bold mb-2 mt-4; }
:deep(.ProseMirror h2) { @apply text-xl font-bold mb-2 mt-3; }
:deep(.ProseMirror h3) { @apply text-lg font-semibold mb-1 mt-3; }
:deep(.ProseMirror p)  { @apply mb-2 leading-relaxed; }
:deep(.ProseMirror ul) { @apply list-disc pl-5 mb-2; }
:deep(.ProseMirror ol) { @apply list-decimal pl-5 mb-2; }
:deep(.ProseMirror blockquote) { @apply border-l-4 border-zinc-400 pl-4 italic text-zinc-500 my-2; }
:deep(.ProseMirror hr) { @apply border-zinc-300 dark:border-zinc-600 my-4; }
:deep(.ProseMirror img) { @apply max-w-full rounded-lg my-2; }
:deep(.ProseMirror video) { @apply w-full rounded-lg my-2 max-h-96; }
:deep(.ProseMirror iframe) { @apply w-full rounded-xl my-2; aspect-ratio: 16/9; }
:deep(.ProseMirror strong) { @apply font-bold; }
:deep(.ProseMirror em) { @apply italic; }
:deep(.ProseMirror u) { @apply underline; }
:deep(.ProseMirror s) { @apply line-through; }
:deep(.ProseMirror a) { @apply text-blue-500 underline; }
:deep(.ProseMirror p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    @apply pointer-events-none float-left h-0 text-zinc-400;
}
</style>
