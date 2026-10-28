<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
})

useHead({
  title: 'Edit & Revisi Artikel — Portal Layanan Mahasiswa',
})

const route = useRoute()
const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const { authHeaders } = useAuth()

const postId = computed(() => route.params.id as string)

interface Category {
  id: number
  name: string
  slug: string
}

interface ReviewHistory {
  id: number
  decision: string
  notes?: string | null
  created_at: string
  reviewer?: { name: string }
}

const categories = ref<Category[]>([])
const title = ref('')
const categoryId = ref<number | ''>('')
const summary = ref('')
const content = ref('')
const coverImagePath = ref('')
const coverImageUrl = ref('')
const currentStatus = ref('')
const rejectionReason = ref('')
const reviews = ref<ReviewHistory[]>([])

const sedangMemuat = ref(true)
const isSaving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const coverInputRef = ref<HTMLInputElement | null>(null)
const uploadingCover = ref(false)

async function muatData() {
  sedangMemuat.value = true
  errorMessage.value = ''
  try {
    const [cats, postData] = await Promise.all([
      $fetch<Category[]>(`${apiUrl}/post-categories`),
      $fetch<any>(`${apiUrl}/my/posts/${postId.value}`, { headers: authHeaders() }),
    ])

    categories.value = cats
    title.value = postData.title
    categoryId.value = postData.category_id
    summary.value = postData.summary
    content.value = postData.content
    coverImagePath.value = postData.cover_image_path || ''
    coverImageUrl.value = postData.cover_image_path
      ? (postData.cover_image_path.startsWith('http')
          ? postData.cover_image_path
          : `${apiUrl.replace('/api', '')}/storage/${postData.cover_image_path}`)
      : ''
    currentStatus.value = postData.status
    rejectionReason.value = postData.rejection_reason || ''
    reviews.value = postData.reviews || []
  } catch (err: any) {
    errorMessage.value = err?.data?.message || 'Gagal memuat detail artikel.'
  } finally {
    sedangMemuat.value = false
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
    errorMessage.value = 'Ukuran file cover maksimal 2MB.'
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

async function simpan(action: 'draft' | 'submit') {
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
    errorMessage.value = 'Ringkasan artikel wajib diisi.'
    return
  }
  if (!content.value.trim() || content.value === '<p></p>') {
    errorMessage.value = 'Konten artikel tidak boleh kosong.'
    return
  }

  isSaving.value = true

  try {
    const payload = {
      title: title.value.trim(),
      category_id: categoryId.value,
      summary: summary.value.trim(),
      content: content.value,
      cover_image_path: coverImagePath.value || null,
      action,
    }

    const res = await $fetch<{ message: string; post: any }>(`${apiUrl}/my/posts/${postId.value}`, {
      method: 'PATCH',
      headers: authHeaders(),
      body: payload,
    })

    successMessage.value = res.message || 'Perubahan artikel berhasil disimpan!'
    setTimeout(() => {
      navigateTo('/blog/artikel-saya')
    }, 1200)
  } catch (err: any) {
    errorMessage.value = err?.data?.message || 'Gagal menyimpan perubahan artikel.'
  } finally {
    isSaving.value = false
  }
}

onMounted(muatData)
</script>

<template>
  <main class="section edit-post-page">
    <div class="container container--narrow">
      <NuxtLink to="/blog/artikel-saya" class="back-link">&larr; Kembali ke Artikel Saya</NuxtLink>

      <div class="page-head">
        <span class="eyebrow">Pembaruan Naskah</span>
        <h1>Edit Artikel & Revisi</h1>
        <p class="muted">Lakukan perbaikan sesuai catatan penolakan petugas, lalu kirim ulang naskah Anda.</p>
      </div>

      <div v-if="successMessage" class="notice" style="margin-bottom: 20px">{{ successMessage }}</div>
      <div v-if="errorMessage" class="notice notice-error" style="margin-bottom: 20px">{{ errorMessage }}</div>

      <!-- Loading State -->
      <div v-if="sedangMemuat" class="card loading-card">
        <p class="muted">Memuat data artikel...</p>
      </div>

      <!-- Cannot Edit Published Notice -->
      <div v-else-if="currentStatus === 'published'" class="card warning-card">
        <h3>Artikel Sudah Dipublikasikan</h3>
        <p class="muted">Artikel ini telah disetujui dan berstatus resmi. Demi integritas informasi, artikel yang sudah tayang tidak dapat diedit langsung oleh kontributor mahasiswa.</p>
        <NuxtLink to="/blog/artikel-saya" class="button button-secondary">Kembali</NuxtLink>
      </div>

      <div v-else class="card edit-form-card">
        <!-- Rejection Warning Alert if rejected -->
        <div v-if="currentStatus === 'rejected'" class="rejection-alert">
          <div class="alert-title">
            ⚠️ <strong>Catatan Penolakan Terakhir dari Petugas:</strong>
          </div>
          <p class="alert-body">{{ rejectionReason || 'Silakan tinjau kembali konten Anda sebelum mengirim ulang.' }}</p>
        </div>

        <!-- Judul -->
        <div class="field">
          <label for="title-input">Judul Artikel *</label>
          <input id="title-input" v-model="title" type="text" required />
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
            <label>Gambar Sampul</label>
            <button
              type="button"
              class="button button-secondary upload-btn"
              :disabled="uploadingCover"
              @click="triggerCoverUpload"
            >
              {{ uploadingCover ? 'Mengunggah...' : '📁 Ubah Gambar Sampul' }}
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

        <!-- Cover Preview -->
        <div v-if="coverImageUrl" class="cover-preview-box">
          <img :src="coverImageUrl" alt="Pratinjau Cover" class="cover-img-preview" />
          <button type="button" class="btn-remove-cover" @click="hapusCover">&times; Hapus Sampul</button>
        </div>

        <!-- Ringkasan Singkat -->
        <div class="field">
          <label for="summary-input">Ringkasan Singkat *</label>
          <textarea id="summary-input" v-model="summary" rows="3" maxlength="600" required />
        </div>

        <!-- Tiptap Rich Text Editor -->
        <div class="field">
          <label>Isi Konten Artikel *</label>
          <TiptapEditor v-model="content" />
        </div>

        <!-- Action Buttons -->
        <div class="form-actions">
          <button
            type="button"
            class="button button-secondary"
            :disabled="isSaving"
            @click="simpan('draft')"
          >
            {{ isSaving ? 'Menyimpan...' : '💾 Simpan Perubahan (Draft)' }}
          </button>

          <button
            type="button"
            class="button"
            :disabled="isSaving"
            @click="simpan('submit')"
          >
            {{ isSaving ? 'Mengirim...' : '🚀 Kirim Ulang untuk Ditinjau Petugas' }}
          </button>
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
  margin-bottom: 12px;
  font-weight: 600;
  font-size: 13px;
  color: var(--green);
}
.page-head {
  margin-bottom: 26px;
}
.page-head h1 {
  font-size: clamp(26px, 4vw, 36px);
  margin: 6px 0 8px;
}
.edit-form-card {
  padding: clamp(20px, 4vw, 36px);
}
.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.upload-btn {
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
.rejection-alert {
  background: #fdf2ef;
  border: 1px solid #f2cfc7;
  border-left: 4px solid #b33924;
  padding: 16px 20px;
  border-radius: 8px;
  margin-bottom: 24px;
}
.alert-title {
  color: #8f2b18;
  font-size: 14px;
  margin-bottom: 4px;
}
.alert-body {
  margin: 0;
  font-size: 14px;
  color: #5a281e;
  line-height: 1.5;
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
.loading-card,
.warning-card {
  text-align: center;
  padding: 50px 20px;
}
@media (max-width: 640px) {
  .form-row-2 {
    grid-template-columns: 1fr;
  }
}
</style>

