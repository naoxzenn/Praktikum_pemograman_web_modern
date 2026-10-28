<script setup lang="ts">
const route = useRoute()
const config = useRuntimeConfig()
const apiUrl = config.public.apiBase

const slug = computed(() => route.params.slug as string)

interface PostDetail {
  id: number
  title: string
  slug: string
  summary: string
  content: string
  cover_image_path?: string | null
  status: string
  published_at?: string
  created_at: string
  author?: { id: number; name: string }
  category?: { id: number; name: string; slug: string; badge_color: string }
}

const post = ref<PostDetail | null>(null)
const sedangMemuat = ref(true)
const tidakDitemukan = ref(false)
const kesalahan = ref('')

async function muatDetail() {
  sedangMemuat.value = true
  tidakDitemukan.value = false
  kesalahan.value = ''
  try {
    const res = await $fetch<PostDetail>(`${apiUrl}/posts/${encodeURIComponent(slug.value)}`)
    post.value = res
  } catch (err: any) {
    if (err?.status === 404 || err?.statusCode === 404) {
      tidakDitemukan.value = true
    } else {
      kesalahan.value = err?.data?.message || 'Gagal memuat artikel.'
    }
  } finally {
    sedangMemuat.value = false
  }
}

useHead(() => ({
  title: post.value ? `${post.value.title} — Portal Layanan Mahasiswa` : 'Detail Artikel',
  meta: [
    {
      name: 'description',
      content: post.value?.summary || 'Artikel resmi Portal Layanan Mahasiswa.',
    },
  ],
}))

function formatTanggal(tgl?: string) {
  if (!tgl) return '—'
  return new Date(tgl).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

onMounted(muatDetail)
</script>

<template>
  <main class="section article-detail-page">
    <div class="container container--narrow">
      <NuxtLink to="/blog" class="back-link">&larr; Kembali ke Daftar Blog</NuxtLink>

      <!-- Loading State -->
      <div v-if="sedangMemuat" class="loading-wrap">
        <p class="muted">Memuat isi artikel...</p>
      </div>

      <!-- 404 / Error State -->
      <div v-else-if="tidakDitemukan" class="card not-found-card">
        <h2>Artikel Tidak Ditemukan</h2>
        <p class="muted">Artikel ini mungkin belum dipublikasikan, telah diarsipkan, atau tautan yang Anda tuju salah.</p>
        <NuxtLink to="/blog" class="button">Kembali ke Blog</NuxtLink>
      </div>

      <div v-else-if="kesalahan" class="notice notice-error">
        {{ kesalahan }}
      </div>

      <!-- Detail Article Content -->
      <article v-else-if="post" class="article-body-wrapper card">
        <header class="article-header">
          <div class="header-meta">
            <span
              class="category-pill"
              :style="{
                backgroundColor: (post.category?.badge_color || '#1e5948') + '18',
                color: post.category?.badge_color || '#1e5948',
              }"
            >
              {{ post.category?.name || 'Umum' }}
            </span>
            <span class="meta-date">{{ formatTanggal(post.published_at || post.created_at) }}</span>
          </div>

          <h1 class="post-title">{{ post.title }}</h1>

          <div class="author-row">
            <div class="author-avatar">{{ (post.author?.name || 'A')[0].toUpperCase() }}</div>
            <div>
              <div class="author-name">{{ post.author?.name || 'Redaksi Kampus' }}</div>
              <span class="author-sub">Penulis Kontributor</span>
            </div>
          </div>
        </header>

        <!-- Cover image -->
        <div v-if="post.cover_image_path" class="post-cover-wrap">
          <img
            :src="post.cover_image_path.startsWith('http') ? post.cover_image_path : `${apiUrl.replace('/api', '')}/storage/${post.cover_image_path}`"
            :alt="post.title"
            class="post-cover-image"
          />
        </div>

        <!-- Summary callout -->
        <div class="summary-box">
          <strong>Ringkasan:</strong>
          <p>{{ post.summary }}</p>
        </div>

        <!-- Rich Text HTML Content (Sanitized on backend) -->
        <div class="article-prose" v-html="post.content" />

        <footer class="article-footer-nav">
          <NuxtLink to="/blog" class="button button-secondary">&larr; Jelajahi Artikel Lainnya</NuxtLink>
        </footer>
      </article>
    </div>
  </main>
</template>

<style scoped>
.container--narrow {
  width: min(840px, calc(100% - 48px));
}
.back-link {
  display: inline-block;
  margin-bottom: 20px;
  font-weight: 600;
  font-size: 14px;
  color: var(--green);
}
.back-link:hover {
  text-decoration: underline;
}
.article-body-wrapper {
  padding: clamp(24px, 5vw, 44px);
}
.header-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.category-pill {
  font-size: 13px;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 999px;
}
.meta-date {
  font-size: 13px;
  color: var(--muted);
}
.post-title {
  font-size: clamp(26px, 4.5vw, 40px);
  font-weight: 800;
  line-height: 1.25;
  margin: 0 0 20px;
  color: var(--green-dark);
}
.author-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 24px;
  border-bottom: 1px solid var(--line);
  margin-bottom: 28px;
}
.author-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--mint);
  color: var(--green-dark);
  font-weight: 800;
  font-size: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.author-name {
  font-weight: 700;
  font-size: 15px;
  color: var(--ink);
}
.author-sub {
  font-size: 12px;
  color: var(--muted);
}
.post-cover-wrap {
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 28px;
  background: #edf1eb;
}
.post-cover-image {
  width: 100%;
  max-height: 440px;
  object-fit: cover;
  display: block;
}
.summary-box {
  background: #f4f8f3;
  border-left: 4px solid var(--green);
  padding: 16px 20px;
  border-radius: 0 8px 8px 0;
  margin-bottom: 32px;
  font-size: 15px;
  line-height: 1.6;
}
.summary-box strong {
  color: var(--green-dark);
  display: block;
  margin-bottom: 4px;
}
.summary-box p {
  margin: 0;
  color: #3b5043;
}
.article-prose {
  font-size: 16px;
  line-height: 1.8;
  color: #24352f;
}
.article-prose :deep(h1) {
  font-size: 26px;
  margin: 28px 0 14px;
  color: var(--green-dark);
}
.article-prose :deep(h2) {
  font-size: 22px;
  margin: 24px 0 12px;
  color: var(--ink);
}
.article-prose :deep(h3) {
  font-size: 18px;
  margin: 20px 0 10px;
}
.article-prose :deep(p) {
  margin-bottom: 16px;
}
.article-prose :deep(ul),
.article-prose :deep(ol) {
  padding-left: 28px;
  margin-bottom: 18px;
}
.article-prose :deep(li) {
  margin-bottom: 6px;
}
.article-prose :deep(blockquote) {
  border-left: 4px solid var(--green);
  padding: 12px 18px;
  background: #f7faf7;
  color: var(--muted);
  font-style: italic;
  margin: 20px 0;
  border-radius: 0 8px 8px 0;
}
.article-prose :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
  margin: 20px 0;
  display: block;
}
.article-prose :deep(a) {
  color: var(--green);
  text-decoration: underline;
}
.article-footer-nav {
  margin-top: 44px;
  padding-top: 24px;
  border-top: 1px solid var(--line);
}
.loading-wrap,
.not-found-card {
  text-align: center;
  padding: 60px 20px;
}
</style>

