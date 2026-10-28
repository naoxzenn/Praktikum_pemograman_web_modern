<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
})

useHead({
  title: 'Artikel Saya — Portal Layanan Mahasiswa',
})

const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const { authHeaders } = useAuth()

interface PostItem {
  id: number
  title: string
  slug: string
  summary: string
  status: 'draft' | 'pending' | 'published' | 'rejected'
  rejection_reason?: string | null
  published_at?: string | null
  created_at: string
  updated_at: string
  category?: { id: number; name: string; badge_color: string }
}

const posts = ref<PostItem[]>([])
const sedangMemuat = ref(true)
const pesan = ref('')
const kesalahan = ref('')
const sedangSubmit = ref<number | null>(null)

async function muatArtikelSaya() {
  sedangMemuat.value = true
  kesalahan.value = ''
  try {
    const data = await $fetch<PostItem[]>(`${apiUrl}/my/posts`, {
      headers: authHeaders(),
    })
    posts.value = data
  } catch (err: any) {
    kesalahan.value = err?.data?.message || 'Gagal mengambil daftar artikel.'
  } finally {
    sedangMemuat.value = false
  }
}

async function kirimUlang(post: PostItem) {
  sedangSubmit.value = post.id
  pesan.value = ''
  kesalahan.value = ''
  try {
    const res = await $fetch<{ message: string; post: PostItem }>(`${apiUrl}/my/posts/${post.id}/submit`, {
      method: 'POST',
      headers: authHeaders(),
    })
    pesan.value = res.message || 'Artikel berhasil dikirim untuk review.'
    post.status = 'pending'
    post.rejection_reason = null
  } catch (err: any) {
    kesalahan.value = err?.data?.message || 'Gagal mengirim artikel.'
  } finally {
    sedangSubmit.value = null
  }
}

function labelStatus(status: string) {
  switch (status) {
    case 'draft':
      return { teks: 'Draft (Konsep)', kelas: 'badge-draft' }
    case 'pending':
      return { teks: 'Menunggu Review', kelas: 'badge-pending' }
    case 'published':
      return { teks: 'Dipublikasikan', kelas: 'badge-published' }
    case 'rejected':
      return { teks: 'Ditolak (Perlu Revisi)', kelas: 'badge-rejected' }
    default:
      return { teks: status, kelas: '' }
  }
}

function formatTanggal(tgl?: string | null) {
  if (!tgl) return '—'
  return new Date(tgl).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

onMounted(muatArtikelSaya)
</script>

<template>
  <main class="section my-posts-page">
    <div class="container">
      <div class="page-top-bar">
        <div>
          <NuxtLink to="/blog" class="back-link">&larr; Kembali ke Blog</NuxtLink>
          <span class="eyebrow">Kontributor Mahasiswa</span>
          <h1>Artikel Saya</h1>
          <p class="muted">Kelola draft naskah, pantau status review petugas, dan perbaiki artikel Anda di sini.</p>
        </div>
        <NuxtLink to="/blog/tulis" class="button">✍️ Tulis Artikel Baru</NuxtLink>
      </div>

      <div v-if="pesan" class="notice" style="margin-bottom: 20px">{{ pesan }}</div>
      <div v-if="kesalahan" class="notice notice-error" style="margin-bottom: 20px">{{ kesalahan }}</div>

      <!-- Loading State -->
      <div v-if="sedangMemuat" class="card empty-card">
        <p class="muted">Mengambil riwayat artikel Anda...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="posts.length === 0" class="card empty-card">
        <h3>Belum Ada Artikel yang Ditulis</h3>
        <p class="muted">Anda belum membuat draft atau mengirimkan artikel kampus.</p>
        <NuxtLink to="/blog/tulis" class="button">Mulai Tulis Artikel Pertama</NuxtLink>
      </div>

      <!-- Posts List -->
      <div v-else class="posts-list-wrap">
        <article v-for="item in posts" :key="item.id" class="card post-item-card">
          <div class="item-header">
            <div>
              <span
                class="category-pill"
                :style="{
                  backgroundColor: (item.category?.badge_color || '#1e5948') + '18',
                  color: item.category?.badge_color || '#1e5948',
                }"
              >
                {{ item.category?.name || 'Umum' }}
              </span>
              <span :class="['status-pill', labelStatus(item.status).kelas]">
                {{ labelStatus(item.status).teks }}
              </span>
            </div>
            <span class="date-text">Diperbarui: {{ formatTanggal(item.updated_at) }}</span>
          </div>

          <h2 class="item-title">{{ item.title }}</h2>
          <p class="item-summary">{{ item.summary }}</p>

          <!-- Rejection Alert Callout if Rejected -->
          <div v-if="item.status === 'rejected'" class="rejection-box">
            <div class="rejection-title">
              ⚠️ <strong>Catatan Revisi dari Petugas:</strong>
            </div>
            <p class="rejection-desc">{{ item.rejection_reason || 'Mohon perbaiki isi konten sesuai pedoman penulisan.' }}</p>
          </div>

          <div class="item-footer">
            <div class="footer-meta">
              <span v-if="item.published_at" class="muted">
                Terbit: {{ formatTanggal(item.published_at) }}
              </span>
            </div>

            <div class="footer-actions">
              <NuxtLink
                v-if="item.status === 'published'"
                :to="`/blog/${item.slug}`"
                class="button button-secondary action-btn"
              >
                Lihat Artikel Publik &rarr;
              </NuxtLink>

              <NuxtLink
                v-if="item.status === 'draft' || item.status === 'rejected'"
                :to="`/blog/artikel-saya/${item.id}`"
                class="button button-secondary action-btn"
              >
                ✏️ Edit & Perbaiki
              </NuxtLink>

              <button
                v-if="item.status === 'draft' || item.status === 'rejected'"
                type="button"
                class="button action-btn"
                :disabled="sedangSubmit === item.id"
                @click="kirimUlang(item)"
              >
                {{ sedangSubmit === item.id ? 'Mengirim...' : '🚀 Kirim untuk Review' }}
              </button>

              <span v-if="item.status === 'pending'" class="pending-notice">
                ⏳ Sedang dalam antrean telaah petugas
              </span>
            </div>
          </div>
        </article>
      </div>
    </div>
  </main>
</template>

<style scoped>
.page-top-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 20px;
  margin-bottom: 30px;
  flex-wrap: wrap;
}
.back-link {
  display: inline-block;
  margin-bottom: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--green);
}
.page-top-bar h1 {
  font-size: clamp(28px, 4vw, 40px);
  margin: 6px 0 8px;
}
.empty-card {
  text-align: center;
  padding: 60px 20px;
}
.posts-list-wrap {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.post-item-card {
  padding: 24px 28px;
  border-radius: 14px;
}
.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  flex-wrap: wrap;
  gap: 10px;
}
.category-pill {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
  margin-right: 8px;
}
.status-pill {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
}
.badge-draft {
  background: #ebeee8;
  color: #556358;
}
.badge-pending {
  background: #fcf1d8;
  color: #8c6819;
}
.badge-published {
  background: #e1eee1;
  color: #276241;
}
.badge-rejected {
  background: #f8e5e0;
  color: #923f2c;
}
.date-text {
  font-size: 13px;
  color: var(--muted);
}
.item-title {
  font-size: 21px;
  font-weight: 700;
  margin: 0 0 10px;
  color: var(--ink);
}
.item-summary {
  font-size: 14px;
  color: var(--muted);
  line-height: 1.6;
  margin: 0 0 18px;
}
.rejection-box {
  background: #fdf2ef;
  border: 1px solid #f2cfc7;
  border-left: 4px solid #b33924;
  padding: 14px 18px;
  border-radius: 8px;
  margin-bottom: 20px;
}
.rejection-title {
  color: #8f2b18;
  font-size: 14px;
  margin-bottom: 4px;
}
.rejection-desc {
  margin: 0;
  font-size: 14px;
  color: #5a281e;
  line-height: 1.5;
}
.item-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid #edf1eb;
  padding-top: 16px;
  flex-wrap: wrap;
  gap: 12px;
}
.footer-actions {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}
.action-btn {
  font-size: 13px;
  min-height: 38px;
  padding: 8px 16px;
}
.pending-notice {
  font-size: 13px;
  font-weight: 600;
  color: #8c6819;
  background: #fcf1d8;
  padding: 8px 14px;
  border-radius: 6px;
}
</style>

