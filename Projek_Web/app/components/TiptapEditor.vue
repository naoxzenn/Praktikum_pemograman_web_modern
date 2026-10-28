<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import Underline from '@tiptap/extension-underline'

const props = withDefaults(
  defineProps<{
    modelValue?: string
    placeholder?: string
    disabled?: boolean
  }>(),
  {
    modelValue: '',
    placeholder: 'Mulai menulis artikel atau pengumuman di sini...',
    disabled: false,
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const { authHeaders } = useAuth()

const fileInputRef = ref<HTMLInputElement | null>(null)
const uploadingImage = ref(false)
const linkPromptOpen = ref(false)
const linkUrl = ref('')

const editor = useEditor({
  content: props.modelValue,
  editable: !props.disabled,
  extensions: [
    StarterKit.configure({
      heading: {
        levels: [1, 2, 3],
      },
    }),
    Underline,
    Link.configure({
      openOnClick: false,
      HTMLAttributes: {
        target: '_blank',
        rel: 'noopener noreferrer',
      },
    }),
    Image.configure({
      inline: false,
      allowBase64: false,
      HTMLAttributes: {
        class: 'tiptap-image',
      },
    }),
    Placeholder.configure({
      placeholder: props.placeholder,
    }),
  ],
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  },
})

// Sinkronkan perubahan modelValue dari luar
watch(
  () => props.modelValue,
  (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
      editor.value.commands.setContent(val || '', { emitUpdate: false })
    }
  }
)

watch(
  () => props.disabled,
  (val) => {
    if (editor.value) {
      editor.value.setEditable(!val)
    }
  }
)

onBeforeUnmount(() => {
  editor.value?.destroy()
})

function setLink() {
  if (!editor.value) return
  const previousUrl = editor.value.getAttributes('link').href || ''
  linkUrl.value = previousUrl
  linkPromptOpen.value = true
}

function applyLink() {
  if (!editor.value) return
  if (linkUrl.value.trim() === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
  } else {
    editor.value
      .chain()
      .focus()
      .extendMarkRange('link')
      .setLink({ href: linkUrl.value.trim() })
      .run()
  }
  linkPromptOpen.value = false
  linkUrl.value = ''
}

function triggerImageUpload() {
  fileInputRef.value?.click()
}

async function onImageSelected(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file || !editor.value) return

  if (file.size > 2 * 1024 * 1024) {
    alert('Ukuran gambar maksimal 2MB.')
    target.value = ''
    return
  }

  const formData = new FormData()
  formData.append('image', file)

  uploadingImage.value = true
  try {
    const res = await $fetch<{ url: string }>(`${apiUrl}/posts/upload-image`, {
      method: 'POST',
      headers: authHeaders(),
      body: formData,
    })

    if (res?.url) {
      editor.value.chain().focus().setImage({ src: res.url }).run()
    }
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal mengunggah gambar.')
  } finally {
    uploadingImage.value = false
    target.value = ''
  }
}
</script>

<template>
  <ClientOnly>
    <div class="tiptap-wrapper" :class="{ 'is-disabled': disabled }">
      <!-- Toolbar -->
      <div v-if="editor" class="tiptap-toolbar" role="toolbar" aria-label="Toolbar Format Artikel">
        <div class="btn-group">
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('paragraph') }"
            title="Paragraf"
            @click="editor.chain().focus().setParagraph().run()"
          >
            P
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('heading', { level: 1 }) }"
            title="Heading 1"
            @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
          >
            H1
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('heading', { level: 2 }) }"
            title="Heading 2"
            @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
          >
            H2
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('heading', { level: 3 }) }"
            title="Heading 3"
            @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
          >
            H3
          </button>
        </div>

        <span class="tb-divider" />

        <div class="btn-group">
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('bold') }"
            title="Tebal (Ctrl+B)"
            @click="editor.chain().focus().toggleBold().run()"
          >
            <strong>B</strong>
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('italic') }"
            title="Miring (Ctrl+I)"
            @click="editor.chain().focus().toggleItalic().run()"
          >
            <em>I</em>
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('underline') }"
            title="Garis Bawah (Ctrl+U)"
            @click="editor.chain().focus().toggleUnderline().run()"
          >
            <u>U</u>
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('strike') }"
            title="Coret"
            @click="editor.chain().focus().toggleStrike().run()"
          >
            <s>S</s>
          </button>
        </div>

        <span class="tb-divider" />

        <div class="btn-group">
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('bulletList') }"
            title="Daftar Poin"
            @click="editor.chain().focus().toggleBulletList().run()"
          >
            • List
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('orderedList') }"
            title="Daftar Nomor"
            @click="editor.chain().focus().toggleOrderedList().run()"
          >
            1. List
          </button>
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('blockquote') }"
            title="Kutipan"
            @click="editor.chain().focus().toggleBlockquote().run()"
          >
            “ ”
          </button>
          <button
            type="button"
            class="tb-btn"
            title="Garis Pemisah"
            @click="editor.chain().focus().setHorizontalRule().run()"
          >
            ―
          </button>
        </div>

        <span class="tb-divider" />

        <div class="btn-group">
          <button
            type="button"
            class="tb-btn"
            :class="{ active: editor.isActive('link') }"
            title="Sisipkan Tautan"
            @click="setLink"
          >
            🔗 Link
          </button>
          <button
            type="button"
            class="tb-btn"
            title="Sisipkan Gambar"
            :disabled="uploadingImage"
            @click="triggerImageUpload"
          >
            {{ uploadingImage ? 'Mengunggah...' : '🖼 Gambar' }}
          </button>
          <input
            ref="fileInputRef"
            type="file"
            accept="image/jpeg,image/png,image/jpg,image/webp"
            style="display: none"
            @change="onImageSelected"
          />
        </div>

        <span class="tb-divider" />

        <div class="btn-group">
          <button
            type="button"
            class="tb-btn"
            title="Hapus Format"
            @click="editor.chain().focus().unsetAllMarks().clearNodes().run()"
          >
            ✕ Bersihkan
          </button>
          <button
            type="button"
            class="tb-btn"
            :disabled="!editor.can().undo()"
            title="Batal Perubahan (Undo)"
            @click="editor.chain().focus().undo().run()"
          >
            ↶
          </button>
          <button
            type="button"
            class="tb-btn"
            :disabled="!editor.can().redo()"
            title="Ulangi Perubahan (Redo)"
            @click="editor.chain().focus().redo().run()"
          >
            ↷
          </button>
        </div>
      </div>

      <!-- Link dialog prompt -->
      <div v-if="linkPromptOpen" class="link-popover">
        <input
          v-model="linkUrl"
          type="url"
          placeholder="https://example.com"
          class="link-input"
          @keyup.enter="applyLink"
        />
        <button type="button" class="button button-sm" @click="applyLink">Pasang</button>
        <button type="button" class="button button-sm button-secondary" @click="linkPromptOpen = false">Batal</button>
      </div>

      <!-- Editor Content Area -->
      <EditorContent :editor="editor" class="tiptap-content-container" />
    </div>

    <template #fallback>
      <div class="tiptap-skeleton">
        <p class="muted">Memuat editor artikel...</p>
      </div>
    </template>
  </ClientOnly>
</template>

<style>
/* Tiptap Editor Styling */
.tiptap-wrapper {
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--paper);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: border-color 0.2s ease;
}

.tiptap-wrapper:focus-within {
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(30, 89, 72, 0.12);
}

.tiptap-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  background: #f4f7f2;
  border-bottom: 1px solid var(--line);
}

.btn-group {
  display: inline-flex;
  gap: 2px;
  align-items: center;
}

.tb-btn {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  padding: 5px 9px;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  cursor: pointer;
  min-height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.tb-btn:hover:not(:disabled) {
  background: #e2ece3;
  color: var(--green-dark);
}

.tb-btn.active {
  background: var(--green);
  color: #fffef9;
  border-color: var(--green);
}

.tb-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.tb-divider {
  width: 1px;
  height: 20px;
  background: #cbd6cb;
  margin: 0 4px;
}

.link-popover {
  display: flex;
  gap: 8px;
  padding: 10px 14px;
  background: #edf3ec;
  border-bottom: 1px solid var(--line);
  align-items: center;
}

.link-input {
  flex: 1;
  min-height: 36px;
  padding: 6px 10px;
  border: 1px solid #cbd6cb;
  border-radius: 6px;
  font-size: 13px;
}

.button-sm {
  min-height: 36px;
  padding: 6px 14px;
  font-size: 12px;
}

/* Editor Prose Area */
.tiptap-content-container {
  min-height: 340px;
  padding: 16px 20px;
  cursor: text;
}

.tiptap-content-container .tiptap {
  outline: none;
  min-height: 300px;
  color: var(--ink);
  line-height: 1.75;
  font-family: inherit;
  font-size: 15px;
}

.tiptap-content-container .tiptap p.is-editor-empty:first-child::before {
  color: var(--muted);
  content: attr(data-placeholder);
  float: left;
  height: 0;
  pointer-events: none;
}

.tiptap-content-container .tiptap h1 {
  font-size: 26px;
  font-weight: 700;
  margin: 24px 0 12px;
  color: var(--green-dark);
}

.tiptap-content-container .tiptap h2 {
  font-size: 21px;
  font-weight: 700;
  margin: 20px 0 10px;
  color: var(--ink);
}

.tiptap-content-container .tiptap h3 {
  font-size: 18px;
  font-weight: 600;
  margin: 16px 0 8px;
}

.tiptap-content-container .tiptap ul,
.tiptap-content-container .tiptap ol {
  padding-left: 24px;
  margin: 12px 0;
}

.tiptap-content-container .tiptap blockquote {
  border-left: 4px solid var(--green);
  padding-left: 16px;
  margin: 16px 0;
  color: var(--muted);
  font-style: italic;
  background: #f7faf7;
  padding-block: 8px;
  border-radius: 0 8px 8px 0;
}

.tiptap-content-container .tiptap hr {
  border: none;
  border-top: 1px solid var(--line);
  margin: 24px 0;
}

.tiptap-content-container .tiptap a {
  color: var(--green);
  text-decoration: underline;
  text-underline-offset: 3px;
}

.tiptap-content-container .tiptap img.tiptap-image {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
  margin: 16px 0;
  display: block;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
}

.tiptap-skeleton {
  min-height: 340px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f9faf8;
  border: 1px dashed var(--line);
  border-radius: 10px;
}
</style>

