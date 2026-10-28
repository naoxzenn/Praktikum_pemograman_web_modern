<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
})

useHead({
  title: 'Tulis Artikel — Portal Layanan Mahasiswa',
})

const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const { user, isMahasiswa, isPetugas, authHeaders } = useAuth()

interface Category {
  id: number
  name: string
  slug: string
  badge_color: string
}

const categories = ref<Category[]>([])
const title = ref('')
const categoryId = ref<number | ''>('')
const summary = ref('')
const content = ref('')
const coverImagePath = ref('')
const coverImageUrl = ref('')

const isSaving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const coverInputRef = ref<HTMLInputElement | null>(null)
const uploadingCover = ref(false)

const slugPreview = computed(() => {
  return title.value
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
})

async function muatKategori() {
  try {
    const data = await $fetch<Category[]>(`${apiUrl}/post-categories`)
    categories.value = data
    if (data.length > 0 && !categoryId.value) {
      categoryId.value = data[0].id
    }
  } catch (err) {
    console.error('Gagal mengambil kategori:', err)
  }
}

function triggerCoverUpload() {
  coverInputRef.value?.click()
}

async function onCoverSelected(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  if (file.size > 2 * 1024 * 1024) {
    errorMessage.value = 'Ukuran gambar cover maksimal 2MB.'
    target.value = ''
    return
  }

  const formData = new FormData()
  formData.append('image', file)

  uploadingCover.value = true
  errorMessage.value = ''
  try {
    const res = await $fetch<{ url: string; path: string }>(`${apiUrl}/posts/upload-image`, {
      method: 'POST',
      headers: authHeaders(),
      body: formData,
    })
    coverImageUrl.value = res.url
    coverImagePath.value = res.path
  } catch (err: any) {
    errorMessage.value = err?.data?.message || 'Gagal mengunggah gambar cover.'
  } finally {
    uploadingCover.value = false
    target.value = ''
  }
}

function hapusCover() {
  coverImagePath.value = ''
  coverImageUrl.value = ''
}

async function simpan(action: 'draft' | 'submit' | 'publish') {
  errorMessage.value = ''
  successMessage.value = ''

  if (!title.value.trim()) {
    errorMessage.value = 'Judul artikel wajib diisi.'
    return
  }
  if (!categoryId.value) {
    errorMessage.value = 'Kategori artikel wajib dipilih.'
    return
  }
  if (!summary.value.trim()) {
    errorMessage.value = 'Ringkasan singkat artikel wajib diisi.'
    return
  }
  if (!content.value.trim() || content.value === '<p></p>') {
    errorMessage.value = 'Konten artikel tidak boleh kosong.'
    return
  }

  isSaving.value = true

  try {
    const endpoint = isPetugas.value ? `${apiUrl}/admin/posts` : `${apiUrl}/posts`

    const payload: Record<string, any> = {
      title: title.value.trim(),
      category_id: categoryId.value,
      summary: summary.value.trim(),
      content: content.value,
      cover_image_path: coverImagePath.value || null,
      action,
    }

    const res = await $fetch<{ message: string; post: any }>(endpoint, {
      method: 'POST',
      headers: authHeaders(),
      body: payload,
    })

    successMessage.value = res.message || 'Artikel berhasil disimpan!'

    setTimeout(() => {
      if (isPetugas.value) {
        navigateTo('/admin')
      } else {
        navigateTo('/blog/artikel-saya')
      }
    }, 1200)
  } catch (err: any) {
    errorMessage.value = err?.data?.message || 'Gagal menyimpan artikel ke server.'
  } finally {
    isSaving.value = false
  }
}

onMounted(muatKategori)
</script>

<template>
  <main class="section write-page">
    <div class="container container--narrow">
      <div class="write-header">
        <div>
          <NuxtLink to="/blog" class="back-link">&larr; Kembali ke Blog</NuxtLink>
          <span class="eyebrow">{{ isPetugas ? 'Redaksi Kampus' : 'Kontributor Mahasiswa' }}</span>
          <h1>{{ isPetugas ? 'Tulis Pengumuman Resmi' : 'Tulis Artikel Baru' }}</h1>
          <p class="muted">
            {{
              isPetugas
                ? 'Sebagai petugas, Anda dapat menerbitkan pengumuman resmi secara langsung atau menyimpannya sebagai draft.'
                : 'Artikel Anda akan melalui proses review oleh petugas sebelum dipublikasikan di halaman utama blog.'
            }}
          </p>
        </div>
      </div>

      <div v-if="successMessage" class="notice" style="margin-bottom: 20px">
        {{ successMessage }}
      </div>
      <div v-if="errorMessage" class="notice notice-error" style="margin-bottom: 20px">
        {{ errorMessage }}
      </div>

      <div class="card write-form-card">
        <!-- Judul -->
        <div class="field">
          <label for="title-input">Judul Artikel / Pengumuman *</label>
          <input
            id="title-input"
            v-model="title"
            type="text"
            placeholder="Contoh: Tata Cara Pengajuan Beasiswa Semester Genap 2026"
            required
          />
          <span v-if="slugPreview" class="slug-hint">
            URL slug: <code>/blog/{{ slugPreview }}</code>
          </span>
        </div>

        <!-- Kategori & Cover -->
        <div class="form-row-2">
          <div class="field">
            <label for="category-select">Kategori *</label>
            <select id="category-select" v-model="categoryId" required>
              <option disabled value="">Pilih Kategori</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>

          <div class="field">
            <label>Gambar Sampul (Opsional, Maks. 2MB)</label>
            <div class="cover-uploader">
              <button
                type="button"
                class="button button-secondary cover-upload-btn"
                :disabled="uploadingCover"
                @click="triggerCoverUpload"
              >
                {{ uploadingCover ? 'Mengunggah...' : '📁 Pilih Gambar Sampul' }}
              </button>
              <input
                ref="coverInputRef"
                type="file"
                accept="image/jpeg,image/png,image/jpg,image/webp"
                style="display: none"
                @change="onCoverSelected"
              />
            </div>
          </div>
        </div>

        <!-- Cover Preview -->
        <div v-if="coverImageUrl" class="cover-preview-box">
          <img :src="coverImageUrl" alt="Pratinjau Cover" class="cover-img-preview" />
          <button type="button" class="btn-remove-cover" @click="hapusCover">&times; Hapus Sampul</button>
        </div>

        <!-- Ringkasan Singkat -->
        <div class="field">
          <label for="summary-input">Ringkasan Singkat (Lead / Abstract) *</label>
          <textarea
            id="summary-input"
            v-model="summary"
            rows="3"
            placeholder="Tuliskan 1-3 kalimat pengantar singkat yang merangkum keseluruhan isi artikel..."
            maxlength="600"
            required
          />
          <span class="char-count">{{ summary.length }} / 600 karakter</span>
        </div>

        <!-- Tiptap Rich Text Editor -->
        <div class="field">
          <label>Isi Konten Artikel *</label>
          <TiptapEditor
            v-model="content"
            placeholder="Tuliskan paragraf, heading, daftar poin, atau sisipkan gambar pendukung di sini..."
          />
        </div>

        <!-- Action Buttons -->
        <div class="form-actions">
          <button
            type="button"
            class="button button-secondary"
            :disabled="isSaving"
            @click="simpan('draft')"
          >
            {{ isSaving ? 'Menyimpan...' : '💾 Simpan sebagai Draft' }}
          </button>

          <template v-if="isMahasiswa">
            <button
              type="button"
              class="button"
              :disabled="isSaving"
              @click="simpan('submit')"
            >
              {{ isSaving ? 'Mengirim...' : '🚀 Kirim untuk Ditinjau Petugas' }}
            </button>
          </template>

          <template v-else-if="isPetugas">
            <button
              type="button"
              class="button"
              :disabled="isSaving"
              @click="simpan('publish')"
            >
              {{ isSaving ? 'Menerbitkan...' : '📢 Terbitkan Pengumuman Sekarang' }}
            </button>
          </template>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.container--narrow {
  width: min(880px, calc(100% - 48px));
}
.back-link {
  display: inline-block;
  margin-bottom: 14px;
  font-weight: 600;
  font-size: 13px;
  color: var(--green);
}
.write-header {
  margin-bottom: 28px;
}
.write-header h1 {
  font-size: clamp(26px, 4vw, 36px);
  margin: 8px 0 10px;
}
.write-form-card {
  padding: clamp(20px, 4vw, 36px);
}
.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.slug-hint {
  font-size: 12px;
  color: var(--muted);
  margin-top: 4px;
}
.slug-hint code {
  background: #edf2eb;
  padding: 2px 6px;
  border-radius: 4px;
  color: var(--green-dark);
}
.char-count {
  font-size: 12px;
  color: var(--muted);
  text-align: right;
}
.cover-upload-btn {
  width: 100%;
}
.cover-preview-box {
  position: relative;
  border-radius: 10px;
  overflow: hidden;
  max-height: 240px;
  margin-bottom: 20px;
  border: 1px solid var(--line);
}
.cover-img-preview {
  width: 100%;
  max-height: 240px;
  object-fit: cover;
  display: block;
}
.btn-remove-cover {
  position: absolute;
  top: 10px;
  right: 10px;
  background: rgba(146, 63, 44, 0.9);
  color: white;
  border: none;
  border-radius: 6px;
  padding: 6px 12px;
  font-size: 12px;
  cursor: pointer;
  font-weight: 600;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 14px;
  margin-top: 32px;
  padding-top: 24px;
  border-top: 1px solid var(--line);
  flex-wrap: wrap;
}
@media (max-width: 640px) {
  .form-row-2 {
    grid-template-columns: 1fr;
  }
}
</style>

