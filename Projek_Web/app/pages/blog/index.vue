<script setup lang="ts">
useHead({
  title: 'Blog & Informasi Kampus — Portal Layanan Mahasiswa',
  meta: [
    {
      name: 'description',
      content: 'Pusat berita, panduan administrasi, pengumuman akademik, dan beasiswa untuk mahasiswa.',
    },
  ],
})

const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const { isLoggedIn, isMahasiswa, isPetugas } = useAuth()

interface Category {
  id: number
  name: string
  slug: string
  badge_color: string
  posts_count?: number
}

interface PostItem {
  id: number
  title: string
  slug: string
  summary: string
  cover_image_path?: string | null
  status: string
  published_at?: string
  created_at: string
  author?: { id: number; name: string }
  category?: { id: number; name: string; slug: string; badge_color: string }
}

interface PaginationMeta {
  current_page: number
  last_page: number
  total: number
}

const categories = ref<Category[]>([])
const posts = ref<PostItem[]>([])
const pagination = ref<PaginationMeta>({ current_page: 1, last_page: 1, total: 0 })

const kategoriAktif = ref('Semua')
const kataKunci = ref('')
const sedangMemuat = ref(true)
const kesalahan = ref('')

async function muatKategori() {
  try {
    const data = await $fetch<Category[]>(`${apiUrl}/post-categories`)
    categories.value = [{ id: 0, name: 'Semua', slug: 'Semua', badge_color: '#1e5948' }, ...data]
  } catch (err) {
    console.error('Gagal mengambil kategori:', err)
  }
}

async function muatArtikel(halaman = 1) {
  sedangMemuat.value = true
  kesalahan.value = ''
  try {
    const query: Record<string, any> = {
      page: halaman,
      per_page: 9,
    }
    if (kategoriAktif.value !== 'Semua') {
      query.category = kategoriAktif.value
    }
    if (kataKunci.value.trim() !== '') {
      query.q = kataKunci.value.trim()
    }

    const res = await $fetch<any>(`${apiUrl}/posts`, { query })
    posts.value = res.data || []
    pagination.value = {
      current_page: res.current_page || 1,
      last_page: res.last_page || 1,
      total: res.total || 0,
    }
  } catch (err: any) {
    kesalahan.value = err?.data?.message || 'Gagal memuat artikel dari server.'
  } finally {
    sedangMemuat.value = false
  }
}

function pilihKategori(kat: string) {
  kategoriAktif.value = kat
  muatArtikel(1)
}

let timeoutCari: any = null
function onCariInput() {
  clearTimeout(timeoutCari)
  timeoutCari = setTimeout(() => {
    muatArtikel(1)
  }, 350)
}

function formatTanggal(tgl?: string) {
  if (!tgl) return '—'
  return new Date(tgl).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

onMounted(() => {
  muatKategori()
  muatArtikel()
})
</script>

<template>
  <main class="blog-page">
    <!-- Hero Section -->
    <section class="hero-blog">
      <div class="container">
        <div class="blog-hero-top">
          <span class="eyebrow" id="blog-eyebrow">Warta & Panduan Kampus</span>

          <!-- Action buttons for logged in users -->
          <div class="blog-actions-bar">
            <template v-if="isMahasiswa">
              <NuxtLink to="/blog/tulis" class="button">✍️ Tulis Artikel</NuxtLink>
              <NuxtLink to="/blog/artikel-saya" class="button button-secondary">📑 Artikel Saya</NuxtLink>
            </template>
            <template v-else-if="isPetugas">
              <NuxtLink to="/blog/tulis" class="button">📢 Buat Pengumuman</NuxtLink>
              <NuxtLink to="/admin" class="button button-secondary">⚙️ Dashboard Petugas</NuxtLink>
            </template>
            <template v-else-if="!isLoggedIn">
              <NuxtLink to="/login" class="button button-secondary" style="font-size: 13px">
                Masuk sebagai Kontributor
              </NuxtLink>
            </template>
          </div>
        </div>

        <h1 id="blog-heading">Blog & Informasi Terkini</h1>
        <p>
          Temukan pengumuman akademik resmi, panduan teknis layanan kemahasiswaan,
          serta informasi beasiswa dan kegiatan kampus dalam satu wadah terpercaya.
        </p>

        <!-- Search bar -->
        <div class="search-box">
          <input
            id="search-article-input"
            v-model="kataKunci"
            type="search"
            placeholder="Cari artikel, panduan layanan, pengumuman..."
            aria-label="Cari artikel blog"
            @input="onCariInput"
          />
        </div>
      </div>
    </section>

    <!-- Main Content Section -->
    <section class="section">
      <div class="container">
        <!-- Filter Tabs -->
        <div class="filter-tabs" role="tablist" aria-label="Filter kategori artikel">
          <button
            v-for="kat in categories"
            :id="`tab-${kat.slug}`"
            :key="kat.id"
            type="button"
            class="tab-btn"
            :class="{ active: kategoriAktif === kat.name }"
            @click="pilihKategori(kat.name)"
          >
            {{ kat.name }}
          </button>
        </div>

        <div v-if="kesalahan" class="notice notice-error" style="margin-bottom: 24px">
          {{ kesalahan }}
        </div>

        <!-- Loading State -->
        <div v-if="sedangMemuat" class="loading-state">
          <p class="muted">Memuat artikel kampus...</p>
        </div>

        <!-- Article Grid -->
        <div v-else-if="posts.length > 0" class="article-grid">
          <NuxtLink
            v-for="item in posts"
            :id="`article-card-${item.id}`"
            :key="item.id"
            :to="`/blog/${item.slug}`"
            class="article-card"
          >
            <!-- Cover image jika ada -->
            <div v-if="item.cover_image_path" class="card-cover-wrap">
              <img
                :src="item.cover_image_path.startsWith('http') ? item.cover_image_path : `${apiUrl.replace('/api', '')}/storage/${item.cover_image_path}`"
                :alt="item.title"
                class="card-cover-img"
              />
            </div>

            <div class="card-body">
              <div class="card-meta">
                <span
                  class="category-pill"
                  :style="{
                    backgroundColor: (item.category?.badge_color || '#1e5948') + '18',
                    color: item.category?.badge_color || '#1e5948',
                  }"
                >
                  {{ item.category?.name || 'Umum' }}
                </span>
                <span class="read-date">{{ formatTanggal(item.published_at || item.created_at) }}</span>
              </div>

              <h2 class="article-title">{{ item.title }}</h2>
              <p class="article-summary">{{ item.summary }}</p>

              <div class="card-footer">
                <div class="author-info">
                  <span class="author-name">{{ item.author?.name || 'Redaksi Kampus' }}</span>
                </div>
                <span class="read-link">Baca Selengkapnya &rarr;</span>
              </div>
            </div>
          </NuxtLink>
        </div>

        <!-- Empty State -->
        <div v-else class="empty-state" id="empty-articles">
          <p class="empty-title">Tidak ada artikel yang cocok</p>
          <p class="muted">Belum ada artikel yang dipublikasikan pada kategori ini atau kata kunci tersebut.</p>
          <button
            class="button button-secondary"
            type="button"
            @click="kategoriAktif = 'Semua'; kataKunci = ''; muatArtikel(1)"
          >
            Reset Pencarian
          </button>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="pagination-bar">
          <button
            type="button"
            class="button button-secondary pagination-btn"
            :disabled="pagination.current_page <= 1"
            @click="muatArtikel(pagination.current_page - 1)"
          >
            &larr; Sebelumnya
          </button>
          <span class="pagination-info">
            Halaman {{ pagination.current_page }} dari {{ pagination.last_page }} ({{ pagination.total }} artikel)
          </span>
          <button
            type="button"
            class="button button-secondary pagination-btn"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="muatArtikel(pagination.current_page + 1)"
          >
            Berikutnya &rarr;
          </button>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.blog-hero-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 12px;
}
.blog-actions-bar {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.hero-blog {
  padding: clamp(48px, 7vw, 84px) 0 36px;
  background: linear-gradient(135deg, #edf2e8 0%, #e3ece1 100%);
  border-bottom: 1px solid var(--line);
}
.hero-blog h1 {
  max-width: 800px;
  margin: 14px 0 16px;
  font-size: clamp(34px, 5vw, 56px);
  color: var(--green-dark);
}
.hero-blog p {
  max-width: 680px;
  font-size: 17px;
  color: #4a5c51;
  margin: 0 0 24px;
}
.search-box input {
  max-width: 580px;
  width: 100%;
  min-height: 48px;
  padding: 12px 18px;
  border: 1px solid #c2d0c2;
  border-radius: 9px;
  background: #fffefb;
  font-size: 15px;
}
.filter-tabs {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 32px;
}
.tab-btn {
  background: #fffef9;
  border: 1px solid var(--line);
  padding: 8px 18px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 600;
  color: #4f5f54;
  cursor: pointer;
  transition: all 0.2s;
}
.tab-btn:hover {
  background: #eef3ec;
  color: var(--green-dark);
}
.tab-btn.active {
  background: var(--green);
  color: #fffef9;
  border-color: var(--green);
}
.article-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 24px;
}
.article-card {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 16px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 0.2s, box-shadow 0.2s;
  cursor: pointer;
  text-decoration: none;
  color: inherit;
}
.article-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 30px rgba(20, 50, 35, 0.08);
}
.card-cover-wrap {
  width: 100%;
  height: 180px;
  overflow: hidden;
  background: #eef2ec;
}
.card-cover-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.card-body {
  padding: 22px;
  display: flex;
  flex-direction: column;
  flex: 1;
}
.card-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.category-pill {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
}
.read-date {
  font-size: 12px;
  color: var(--muted);
}
.article-title {
  font-size: 19px;
  font-weight: 700;
  line-height: 1.35;
  margin: 0 0 10px;
  color: var(--ink);
}
.article-summary {
  font-size: 14px;
  color: var(--muted);
  line-height: 1.6;
  margin: 0 0 20px;
  flex: 1;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid #edf1eb;
  padding-top: 14px;
}
.author-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}
.read-link {
  font-size: 13px;
  font-weight: 700;
  color: var(--green);
}
.loading-state,
.empty-state {
  text-align: center;
  padding: 48px 16px;
  background: var(--paper);
  border: 1px dashed var(--line);
  border-radius: 14px;
}
.empty-title {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 6px;
}
.pagination-bar {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 36px;
}
.pagination-info {
  font-size: 14px;
  color: var(--muted);
}
@media (max-width: 900px) {
  .article-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 600px) {
  .article-grid {
    grid-template-columns: 1fr;
  }
}
</style>

