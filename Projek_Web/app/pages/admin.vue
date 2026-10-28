<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
})

const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const { authHeaders } = useAuth()

interface Pengajuan {
  id: number
  nomor_pengajuan: string
  nama_mahasiswa: string
  nim: string
  email: string
  status: string
  keterangan?: string
  dokumen_path?: string
  dokumen_nama_asli?: string
  catatan_revisi?: string
  estimasi_selesai?: string
  created_at: string
  layanan?: { nama: string }
}

interface ActivityLogItem {
  id: number
  role: string
  action: string
  description: string
  created_at: string
  ip_address?: string
  user?: { name: string; email: string; role: string }
}

interface AdminPost {
  id: number
  title: string
  slug: string
  summary: string
  content: string
  cover_image_path?: string | null
  status: 'draft' | 'pending' | 'published' | 'rejected'
  rejection_reason?: string | null
  published_at?: string | null
  created_at: string
  updated_at: string
  author?: { id: number; name: string; role?: string }
  category?: { id: number; name: string; badge_color: string }
  reviews?: Array<{ id: number; decision: string; notes?: string; created_at: string; reviewer?: { name: string } }>
}

// ── Tab Navigasi Utama ──────────────────────────────────────────────────────
const activeTab = ref<'pengajuan' | 'blog' | 'logs'>('pengajuan')

// ── State Pengajuan Layanan (Enhanced) ──────────────────────────────────────
const pengajuan = ref<Pengajuan[]>([])
const pencarian = ref('')
const filterStatus = ref('Semua')
const sedangMemuat = ref(false)
const pesan = ref('')
const kesalahan = ref('')
const sedangMemperbarui = ref<string | null>(null)

// Paginasi Backend
const halaman = ref(1)
const totalHalaman = ref(1)
const totalData = ref(0)
const perHalaman = ref(10)

const pilihanStatus = ['Menunggu', 'Diproses', 'Perlu Perbaikan', 'Selesai', 'Ditolak']

// Modal Minta Revisi
const isModalMintaRevisiOpen = ref(false)
const pengajuanDipilih = ref<Pengajuan | null>(null)
const catatanRevisiText = ref('')
const sedangKirimRevisi = ref(false)

// State Log Aktivitas
const logs = ref<ActivityLogItem[]>([])
const sedangMemuatLogs = ref(false)

// Statistik Backend Real-time
const backendStats = ref({
  pengajuan: {
    total: 0,
    menunggu: 0,
    diproses: 0,
    perlu_perbaikan: 0,
    selesai: 0,
    ditolak: 0,
    terlambat: 0,
    hari_ini: 0,
  },
  blog: {
    pending: 0,
    published: 0,
  },
})

const daftarPengajuan = computed(() => {
  const kata = pencarian.value.toLowerCase().trim()
  return pengajuan.value.filter((item) => {
    const cocokKata = [
      item.nama_mahasiswa,
      item.nim,
      item.nomor_pengajuan,
      item.layanan?.nama || '',
    ].some((nilai) => nilai.toLowerCase().includes(kata))
    const cocokStatus = filterStatus.value === 'Semua' || item.status === filterStatus.value
    return cocokKata && cocokStatus
  })
})

const statistik = computed(() => ({
  total: pengajuan.value.length,
  menunggu: pengajuan.value.filter(item => item.status === 'Menunggu').length,
  diproses: pengajuan.value.filter(item => item.status === 'Diproses').length,
  selesai: pengajuan.value.filter(item => item.status === 'Selesai').length,
  ditolak: pengajuan.value.filter(item => item.status === 'Ditolak').length,
}))

// ── State Blog & Moderasi (New) ─────────────────────────────────────────────
const blogSubTab = ref<'pending' | 'all'>('pending')
const adminPosts = ref<AdminPost[]>([])
const pendingPosts = ref<AdminPost[]>([])
const sedangMemuatBlog = ref(false)
const pesanBlog = ref('')
const kesalahanBlog = ref('')

const blogSearch = ref('')
const blogFilterStatus = ref('Semua')

const selectedPost = ref<AdminPost | null>(null)
const isReviewModalOpen = ref(false)
const isRejectModalOpen = ref(false)
const rejectReason = ref('')
const approveNote = ref('')
const sedangAksiModerasi = ref(false)

const statistikBlog = computed(() => ({
  total: adminPosts.value.length,
  pending: pendingPosts.value.length,
  published: adminPosts.value.filter(p => p.status === 'published').length,
  rejected: adminPosts.value.filter(p => p.status === 'rejected').length,
  draft: adminPosts.value.filter(p => p.status === 'draft').length,
}))

const daftarSemuaArtikel = computed(() => {
  const kata = blogSearch.value.toLowerCase().trim()
  return adminPosts.value.filter((p) => {
    const cocokKata = kata === '' ||
      p.title.toLowerCase().includes(kata) ||
      p.summary.toLowerCase().includes(kata) ||
      (p.author?.name || '').toLowerCase().includes(kata)
    const cocokStatus = blogFilterStatus.value === 'Semua' || p.status === blogFilterStatus.value
    return cocokKata && cocokStatus
  })
})

// ── API Pengajuan & Statistik (Enhanced) ────────────────────────────────────
async function muatStatistik() {
  try {
    const res = await $fetch<any>(`${apiUrl}/admin/pengajuan/statistics`, {
      headers: authHeaders(),
    })
    if (res?.pengajuan) {
      backendStats.value = res
    }
  } catch (err) {
    // Fallback jika belum siap
  }
}

async function muatPengajuan(p = 1) {
  halaman.value = p
  sedangMemuat.value = true
  kesalahan.value = ''
  try {
    const token = sessionStorage.getItem('portal_token')
    if (!token) {
      await navigateTo('/login')
      return
    }

    const queryParams = new URLSearchParams({
      page: String(halaman.value),
      per_page: String(perHalaman.value),
      paginate: 'true',
    })
    if (pencarian.value.trim()) {
      queryParams.set('search', pencarian.value.trim())
    }
    if (filterStatus.value !== 'Semua') {
      queryParams.set('status', filterStatus.value)
    }

    const res = await $fetch<any>(`${apiUrl}/pengajuan?${queryParams.toString()}`, {
      headers: authHeaders(),
    })

    if (res && res.data && typeof res.current_page !== 'undefined') {
      pengajuan.value = res.data
      halaman.value = res.current_page
      totalHalaman.value = res.last_page
      totalData.value = res.total
    } else if (Array.isArray(res)) {
      pengajuan.value = res
      totalData.value = res.length
    }
  } catch (error: any) {
    if (error.status === 401 || error.statusCode === 401) {
      await navigateTo('/login')
      return
    }
    kesalahan.value = error.data?.message || 'Gagal mengambil data pengajuan.'
  } finally {
    sedangMemuat.value = false
  }
}

async function unduhDokumenPengajuan(item: Pengajuan) {
  try {
    const blob = await $fetch<Blob>(
      `${apiUrl}/pengajuan/${encodeURIComponent(item.nomor_pengajuan)}/dokumen`,
      {
        headers: authHeaders(),
        responseType: 'blob',
      }
    )
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = item.dokumen_nama_asli || `dokumen_${item.nomor_pengajuan}.pdf`
    document.body.appendChild(a)
    a.click()
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)
  } catch (err: any) {
    alert(err?.data?.message || 'Berkas dokumen tidak tersedia atau gagal diunduh.')
  }
}

function bukaModalMintaRevisi(item: Pengajuan) {
  pengajuanDipilih.value = item
  catatanRevisiText.value = item.catatan_revisi || ''
  isModalMintaRevisiOpen.value = true
}

function tutupModalMintaRevisi() {
  isModalMintaRevisiOpen.value = false
  pengajuanDipilih.value = null
  catatanRevisiText.value = ''
}

async function kirimPermintaanRevisi() {
  if (!catatanRevisiText.value.trim() || !pengajuanDipilih.value) return
  sedangKirimRevisi.value = true
  pesan.value = ''
  kesalahan.value = ''
  try {
    await $fetch(
      `${apiUrl}/pengajuan/${encodeURIComponent(pengajuanDipilih.value.nomor_pengajuan)}/minta-revisi`,
      {
        method: 'POST',
        headers: authHeaders(),
        body: { catatan_revisi: catatanRevisiText.value.trim() },
      }
    )
    pesan.value = `Permintaan perbaikan dokumen untuk ${pengajuanDipilih.value.nomor_pengajuan} berhasil dikirim.`
    tutupModalMintaRevisi()
    await muatPengajuan(halaman.value)
    await muatStatistik()
  } catch (err: any) {
    kesalahan.value = err?.data?.message || 'Gagal mengirim permintaan perbaikan.'
  } finally {
    sedangKirimRevisi.value = false
  }
}

async function eksporCsv() {
  try {
    const queryParams = new URLSearchParams()
    if (filterStatus.value !== 'Semua') queryParams.set('status', filterStatus.value)

    const blob = await $fetch<Blob>(`${apiUrl}/admin/pengajuan/export?${queryParams.toString()}`, {
      headers: authHeaders(),
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `laporan_pengajuan_${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(a)
    a.click()
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)
  } catch (err: any) {
    alert(err?.data?.message || 'Gagal mengekspor laporan pengajuan.')
  }
}

async function muatLogs() {
  sedangMemuatLogs.value = true
  try {
    const res = await $fetch<any>(`${apiUrl}/admin/activity-logs`, {
      headers: authHeaders(),
    })
    logs.value = res.data || res
  } catch (err) {
    // Abaikan jika gagal
  } finally {
    sedangMemuatLogs.value = false
  }
}

async function ubahStatus(item: Pengajuan, status: string) {
  if (status === item.status) return
  const statusLama = item.status
  sedangMemperbarui.value = item.nomor_pengajuan
  pesan.value = ''
  kesalahan.value = ''
  try {
    await $fetch(`${apiUrl}/pengajuan/${encodeURIComponent(item.nomor_pengajuan)}/status`, {
      method: 'PATCH',
      headers: authHeaders(),
      body: { status },
    })
    item.status = status
    pesan.value = `Status ${item.nomor_pengajuan} berhasil diubah menjadi ${status}.`
    await muatStatistik()
  } catch (error: any) {
    item.status = statusLama
    kesalahan.value = error.data?.message || 'Gagal memperbarui status pengajuan.'
  } finally {
    sedangMemperbarui.value = null
  }
}

// ── API Blog & Moderasi (New) ───────────────────────────────────────────────
async function muatDataBlog() {
  sedangMemuatBlog.value = true
  kesalahanBlog.value = ''
  try {
    const [pendingRes, allRes] = await Promise.all([
      $fetch<AdminPost[]>(`${apiUrl}/admin/posts/pending`, { headers: authHeaders() }),
      $fetch<any>(`${apiUrl}/admin/posts?per_page=50`, { headers: authHeaders() }),
    ])
    pendingPosts.value = pendingRes || []
    adminPosts.value = allRes?.data || []
  } catch (err: any) {
    kesalahanBlog.value = err?.data?.message || 'Gagal mengambil data artikel blog.'
  } finally {
    sedangMemuatBlog.value = false
  }
}

function bukaTinjau(post: AdminPost) {
  selectedPost.value = post
  approveNote.value = ''
  rejectReason.value = ''
  isReviewModalOpen.value = true
}

function tutupModalTinjau() {
  isReviewModalOpen.value = false
  selectedPost.value = null
}

function bukaDialogTolak(post: AdminPost) {
  selectedPost.value = post
  rejectReason.value = ''
  isRejectModalOpen.value = true
}

function tutupDialogTolak() {
  isRejectModalOpen.value = false
}

async function setujuiArtikel(post: AdminPost) {
  sedangAksiModerasi.value = true
  pesanBlog.value = ''
  kesalahanBlog.value = ''
  try {
    const res = await $fetch<{ message: string; post: AdminPost }>(
      `${apiUrl}/admin/posts/${post.id}/approve`,
      {
        method: 'POST',
        headers: authHeaders(),
        body: { notes: approveNote.value || 'Artikel disetujui untuk publikasi.' },
      }
    )
    pesanBlog.value = res.message || 'Artikel berhasil disetujui dan dipublikasikan!'
    tutupModalTinjau()
    await muatDataBlog()
  } catch (err: any) {
    kesalahanBlog.value = err?.data?.message || 'Gagal menyetujui artikel.'
  } finally {
    sedangAksiModerasi.value = false
  }
}

async function konfirmasiTolak() {
  if (!selectedPost.value) return
  if (!rejectReason.value.trim() || rejectReason.value.trim().length < 5) {
    alert('Alasan penolakan wajib diisi minimal 5 karakter agar mahasiswa dapat melakukan perbaikan.')
    return
  }

  sedangAksiModerasi.value = true
  pesanBlog.value = ''
  kesalahanBlog.value = ''
  try {
    const res = await $fetch<{ message: string; post: AdminPost }>(
      `${apiUrl}/admin/posts/${selectedPost.value.id}/reject`,
      {
        method: 'POST',
        headers: authHeaders(),
        body: { reason: rejectReason.value.trim() },
      }
    )
    pesanBlog.value = res.message || 'Artikel berhasil ditolak dengan catatan revisi.'
    tutupDialogTolak()
    tutupModalTinjau()
    await muatDataBlog()
  } catch (err: any) {
    kesalahanBlog.value = err?.data?.message || 'Gagal menolak artikel.'
  } finally {
    sedangAksiModerasi.value = false
  }
}

async function arsipkanArtikel(post: AdminPost) {
  if (!confirm(`Apakah Anda yakin ingin mengarsipkan artikel "${post.title}"? Status akan diubah menjadi draft.`)) return
  try {
    const res = await $fetch<{ message: string }>(`${apiUrl}/admin/posts/${post.id}/archive`, {
      method: 'POST',
      headers: authHeaders(),
    })
    pesanBlog.value = res.message || 'Artikel berhasil diarsipkan.'
    await muatDataBlog()
  } catch (err: any) {
    kesalahanBlog.value = err?.data?.message || 'Gagal mengarsipkan artikel.'
  }
}

async function publikasikanDraft(post: AdminPost) {
  try {
    const res = await $fetch<{ message: string }>(`${apiUrl}/admin/posts/${post.id}/publish`, {
      method: 'POST',
      headers: authHeaders(),
    })
    pesanBlog.value = res.message || 'Artikel berhasil dipublikasikan.'
    await muatDataBlog()
  } catch (err: any) {
    kesalahanBlog.value = err?.data?.message || 'Gagal memublikasikan artikel.'
  }
}

function labelStatusBlog(status: string) {
  switch (status) {
    case 'draft':
      return { teks: 'Draft', kelas: 'badge-draft' }
    case 'pending':
      return { teks: 'Menunggu Review', kelas: 'badge-pending' }
    case 'published':
      return { teks: 'Dipublikasikan', kelas: 'badge-published' }
    case 'rejected':
      return { teks: 'Ditolak', kelas: 'badge-rejected' }
    default:
      return { teks: status, kelas: '' }
  }
}

function formatTanggal(tgl?: string | null) {
  if (!tgl) return '—'
  return new Date(tgl).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

watch(activeTab, (tab) => {
  if (tab === 'logs') {
    muatLogs()
  }
})

onMounted(() => {
  muatPengajuan(1)
  muatStatistik()
  muatDataBlog()
})
</script>

<template>
  <main class="section admin-page">
    <div class="container">
      <div class="admin-heading">
        <div>
          <span class="eyebrow">Ruang kerja petugas</span>
          <h1>Dashboard Administrasi & Moderasi</h1>
          <p>Kelola permohonan layanan mahasiswa, moderasi artikel kontributor, dan terbitkan pengumuman resmi.</p>
        </div>
        <div class="heading-actions">
          <NuxtLink to="/blog/tulis" class="button" style="white-space: nowrap">
            📢 Buat Pengumuman Baru
          </NuxtLink>
        </div>
      </div>

      <!-- Main Navigation Tabs -->
      <div class="admin-nav-tabs">
        <button
          type="button"
          class="nav-tab-btn"
          :class="{ active: activeTab === 'pengajuan' }"
          @click="activeTab = 'pengajuan'"
        >
          📋 Pengajuan Layanan ({{ backendStats.pengajuan.total || totalData }})
        </button>
        <button
          type="button"
          class="nav-tab-btn"
          :class="{ active: activeTab === 'blog' }"
          @click="activeTab = 'blog'"
        >
          📰 Moderasi & CMS Blog
          <span v-if="backendStats.blog.pending > 0 || statistikBlog.pending > 0" class="tab-badge-pending">
            {{ backendStats.blog.pending || statistikBlog.pending }}
          </span>
        </button>
        <button
          type="button"
          class="nav-tab-btn"
          :class="{ active: activeTab === 'logs' }"
          @click="activeTab = 'logs'"
        >
          📜 Log Audit Aktivitas
        </button>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <!-- TAB 1: PENGAJUAN LAYANAN (ENHANCED DENGAN SLA, DOKUMEN & REVISI)     -->
      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <section v-if="activeTab === 'pengajuan'">
        <div class="admin-statistics">
          <article class="stat-card">
            <p class="stat-label">Total pengajuan</p>
            <p class="stat-value">{{ backendStats.pengajuan.total }}</p>
          </article>
          <article class="stat-card stat-card--waiting">
            <p class="stat-label">Menunggu</p>
            <p class="stat-value">{{ backendStats.pengajuan.menunggu }}</p>
          </article>
          <article class="stat-card stat-card--progress">
            <p class="stat-label">Diproses</p>
            <p class="stat-value">{{ backendStats.pengajuan.diproses }}</p>
          </article>
          <article class="stat-card" style="border-left: 4px solid #f59e0b">
            <p class="stat-label">Perlu Perbaikan</p>
            <p class="stat-value" style="color: #b45309">{{ backendStats.pengajuan.perlu_perbaikan }}</p>
          </article>
          <article class="stat-card stat-card--done">
            <p class="stat-label">Selesai</p>
            <p class="stat-value">{{ backendStats.pengajuan.selesai }}</p>
          </article>
          <article class="stat-card stat-card--rejected">
            <p class="stat-label">Ditolak</p>
            <p class="stat-value">{{ backendStats.pengajuan.ditolak }}</p>
          </article>
          <article v-if="backendStats.pengajuan.terlambat > 0" class="stat-card" style="border-left: 4px solid #dc2626">
            <p class="stat-label">⚠️ Lewat SLA</p>
            <p class="stat-value" style="color: #dc2626">{{ backendStats.pengajuan.terlambat }}</p>
          </article>
        </div>

        <div v-if="pesan" class="notice" role="status" style="margin-bottom: 16px">{{ pesan }}</div>
        <div v-if="kesalahan" class="notice notice-error" role="alert" style="margin-bottom: 16px">{{ kesalahan }}</div>

        <section class="card">
          <div class="section-heading">
            <div>
              <span class="eyebrow">Administrasi</span>
              <h2>Daftar pengajuan</h2>
            </div>
            <div style="display: flex; gap: 8px; align-items: center">
              <span class="muted">{{ totalData }} total pengajuan</span>
              <button class="button button-secondary" type="button" @click="eksporCsv">
                📥 Ekspor CSV
              </button>
            </div>
          </div>

          <div class="admin-tools">
            <div class="field">
              <label for="cari">Cari pengajuan</label>
              <input id="cari" v-model="pencarian" type="search" placeholder="Nama, NIM, nomor, atau layanan" @keyup.enter="muatPengajuan(1)" />
            </div>
            <div class="field">
              <label for="filter">Status pengajuan</label>
              <select id="filter" v-model="filterStatus" @change="muatPengajuan(1)">
                <option>Semua</option>
                <option v-for="status in pilihanStatus" :key="status">{{ status }}</option>
              </select>
            </div>
            <button class="button" type="button" :disabled="sedangMemuat" @click="muatPengajuan(1)">
              {{ sedangMemuat ? 'Memuat...' : 'Cari / Segarkan' }}
            </button>
          </div>

          <div v-if="sedangMemuat" class="muted" style="padding: 28px 0">Mengambil data dari server...</div>
          <div v-else-if="daftarPengajuan.length === 0" class="muted" style="padding: 28px 0">
            Tidak ada pengajuan yang cocok dengan pencarian ini.
          </div>

          <div v-else class="table-wrap" style="margin-top: 26px">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Mahasiswa</th>
                  <th>Layanan & Dokumen</th>
                  <th>Tanggal & SLA</th>
                  <th>Status</th>
                  <th>Aksi / Perbarui</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in pengajuan" :key="item.id">
                  <td>
                    <div class="student-name">{{ item.nama_mahasiswa }}</div>
                    <span class="table-meta">NIM {{ item.nim }}</span>
                    <span class="table-meta">{{ item.nomor_pengajuan }}</span>
                  </td>
                  <td>
                    <div>{{ item.layanan?.nama || '—' }}</div>
                    <div v-if="item.dokumen_path" style="margin-top: 6px">
                      <button
                        class="button-link"
                        type="button"
                        style="font-size: 12px; color: var(--green); text-decoration: underline"
                        @click="unduhDokumenPengajuan(item)"
                      >
                        📄 Unduh {{ item.dokumen_nama_asli || 'Berkas' }}
                      </button>
                    </div>
                  </td>
                  <td>
                    <div>{{ new Date(item.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) }}</div>
                    <small v-if="item.estimasi_selesai" class="table-meta" style="color: #6b7280">
                      SLA: {{ new Date(item.estimasi_selesai).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }) }}
                    </small>
                  </td>
                  <td>
                    <AppBadge :status="item.status" />
                    <div v-if="item.catatan_revisi && item.status === 'Perlu Perbaikan'" style="margin-top: 4px; font-size: 11px; color: #b45309">
                      Catatan: {{ item.catatan_revisi }}
                    </div>
                  </td>
                  <td>
                    <div style="display: flex; flex-direction: column; gap: 6px">
                      <select
                        class="table-status"
                        :value="item.status"
                        :disabled="sedangMemperbarui === item.nomor_pengajuan"
                        :aria-label="`Ubah status ${item.nomor_pengajuan}`"
                        @change="ubahStatus(item, ($event.target as HTMLSelectElement).value)"
                      >
                        <option v-for="status in pilihanStatus" :key="status">{{ status }}</option>
                      </select>
                      <button
                        v-if="!['Selesai', 'Ditolak'].includes(item.status)"
                        class="button button-secondary"
                        type="button"
                        style="font-size: 11px; padding: 4px 8px; min-height: auto"
                        @click="bukaModalMintaRevisi(item)"
                      >
                        ⚠️ Minta Revisi
                      </button>
                    </div>
                    <span v-if="sedangMemperbarui === item.nomor_pengajuan" class="table-meta">Menyimpan...</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination Controls -->
          <div v-if="totalHalaman > 1" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb">
            <span class="muted" style="font-size: 13px">Halaman {{ halaman }} dari {{ totalHalaman }} (Total {{ totalData }} data)</span>
            <div style="display: flex; gap: 8px">
              <button
                class="button button-secondary"
                type="button"
                style="padding: 6px 12px; font-size: 13px"
                :disabled="halaman <= 1 || sedangMemuat"
                @click="muatPengajuan(halaman - 1)"
              >
                ◀ Sebelumnya
              </button>
              <button
                class="button button-secondary"
                type="button"
                style="padding: 6px 12px; font-size: 13px"
                :disabled="halaman >= totalHalaman || sedangMemuat"
                @click="muatPengajuan(halaman + 1)"
              >
                Selanjutnya ▶
              </button>
            </div>
          </div>
        </section>
      </section>

      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <!-- TAB 2: MODERASI & CMS BLOG (FITUR LENGKAP ARTIKEL MAHASISWA & RESMI) -->
      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <section v-else-if="activeTab === 'blog'">
        <!-- Blog Statistics -->
        <div class="admin-statistics">
          <article class="stat-card">
            <p class="stat-label">Total Artikel</p>
            <p class="stat-value">{{ statistikBlog.total }}</p>
          </article>
          <article class="stat-card stat-card--waiting">
            <p class="stat-label">Antrean Review</p>
            <p class="stat-value">{{ statistikBlog.pending }}</p>
          </article>
          <article class="stat-card stat-card--done">
            <p class="stat-label">Dipublikasikan</p>
            <p class="stat-value">{{ statistikBlog.published }}</p>
          </article>
          <article class="stat-card stat-card--rejected">
            <p class="stat-label">Ditolak</p>
            <p class="stat-value">{{ statistikBlog.rejected }}</p>
          </article>
          <article class="stat-card">
            <p class="stat-label">Draft</p>
            <p class="stat-value">{{ statistikBlog.draft }}</p>
          </article>
        </div>

        <div v-if="pesanBlog" class="notice" role="status" style="margin-bottom: 16px">{{ pesanBlog }}</div>
        <div v-if="kesalahanBlog" class="notice notice-error" role="alert" style="margin-bottom: 16px">{{ kesalahanBlog }}</div>

        <!-- Sub Tabs for Blog -->
        <div class="blog-subtabs">
          <button
            type="button"
            class="subtab-btn"
            :class="{ active: blogSubTab === 'pending' }"
            @click="blogSubTab = 'pending'"
          >
            ⏳ Antrean Review
            <span class="subtab-badge" v-if="pendingPosts.length > 0">{{ pendingPosts.length }}</span>
          </button>
          <button
            type="button"
            class="subtab-btn"
            :class="{ active: blogSubTab === 'all' }"
            @click="blogSubTab = 'all'"
          >
            📚 Semua Artikel & Pengumuman ({{ adminPosts.length }})
          </button>
        </div>

        <!-- VIEW 1: ANTREAN REVIEW (PENDING) -->
        <section v-if="blogSubTab === 'pending'" class="card">
          <div class="section-heading">
            <div>
              <span class="eyebrow">Moderasi Mahasiswa</span>
              <h2>Antrean Naskah Menunggu Peninjauan</h2>
            </div>
            <button class="button button-secondary" type="button" :disabled="sedangMemuatBlog" @click="muatDataBlog">
              {{ sedangMemuatBlog ? 'Menyinkronkan...' : '↻ Muat Ulang Antrean' }}
            </button>
          </div>

          <div v-if="sedangMemuatBlog" class="muted" style="padding: 28px 0">Mengambil antrean artikel...</div>
          <div v-else-if="pendingPosts.length === 0" class="muted empty-box">
            <p>🎉 Tidak ada artikel yang sedang menunggu review saat ini.</p>
            <p class="muted" style="font-size: 13px">Seluruh kiriman mahasiswa telah diproses.</p>
          </div>

          <div v-else class="table-wrap" style="margin-top: 18px">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Judul & Ringkasan</th>
                  <th>Penulis (Kontributor)</th>
                  <th>Kategori</th>
                  <th>Tanggal Kirim</th>
                  <th>Aksi Moderasi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in pendingPosts" :key="item.id">
                  <td style="max-width: 320px">
                    <strong class="student-name">{{ item.title }}</strong>
                    <span class="table-meta" style="line-height: 1.4">{{ item.summary }}</span>
                  </td>
                  <td>
                    <div>{{ item.author?.name || 'Mahasiswa' }}</div>
                    <span class="table-meta">{{ item.author?.role || 'mahasiswa' }}</span>
                  </td>
                  <td>
                    <span
                      class="category-pill"
                      :style="{
                        backgroundColor: (item.category?.badge_color || '#1e5948') + '18',
                        color: item.category?.badge_color || '#1e5948',
                      }"
                    >
                      {{ item.category?.name || 'Umum' }}
                    </span>
                  </td>
                  <td>{{ formatTanggal(item.created_at) }}</td>
                  <td>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap">
                      <button
                        type="button"
                        class="button button-sm"
                        @click="bukaTinjau(item)"
                      >
                        🔍 Periksa & Tinjau
                      </button>
                      <button
                        type="button"
                        class="button button-sm button-secondary"
                        style="color: #923f2c; border-color: #e5b9b0"
                        @click="bukaDialogTolak(item)"
                      >
                        Tolak
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- VIEW 2: SEMUA ARTIKEL -->
        <section v-else-if="blogSubTab === 'all'" class="card">
          <div class="section-heading">
            <div>
              <span class="eyebrow">Daftar Lengkap</span>
              <h2>Semua Artikel & Pengumuman</h2>
            </div>
            <span class="muted">{{ daftarSemuaArtikel.length }} artikel ditemukan</span>
          </div>

          <div class="admin-tools">
            <div class="field">
              <label for="blog-cari">Cari judul, ringkasan, atau penulis</label>
              <input id="blog-cari" v-model="blogSearch" type="search" placeholder="Ketik kata kunci..." />
            </div>
            <div class="field">
              <label for="blog-status-filter">Filter Status</label>
              <select id="blog-status-filter" v-model="blogFilterStatus">
                <option>Semua</option>
                <option value="published">Dipublikasikan</option>
                <option value="pending">Menunggu Review</option>
                <option value="draft">Draft</option>
                <option value="rejected">Ditolak</option>
              </select>
            </div>
            <button class="button" type="button" :disabled="sedangMemuatBlog" @click="muatDataBlog">
              Terapkan
            </button>
          </div>

          <div v-if="sedangMemuatBlog" class="muted" style="padding: 28px 0">Mengambil data artikel...</div>
          <div v-else-if="daftarSemuaArtikel.length === 0" class="muted empty-box">
            Tidak ada artikel yang cocok dengan pencarian ini.
          </div>

          <div v-else class="table-wrap" style="margin-top: 24px">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Judul</th>
                  <th>Penulis</th>
                  <th>Kategori</th>
                  <th>Status</th>
                  <th>Tanggal Terbit</th>
                  <th>Aksi Editorial</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in daftarSemuaArtikel" :key="item.id">
                  <td style="max-width: 280px">
                    <strong class="student-name">{{ item.title }}</strong>
                    <NuxtLink v-if="item.status === 'published'" :to="`/blog/${item.slug}`" target="_blank" class="table-meta" style="color: var(--green)">
                      ↗ Lihat publik
                    </NuxtLink>
                  </td>
                  <td>{{ item.author?.name || '—' }} ({{ item.author?.role || 'user' }})</td>
                  <td>{{ item.category?.name || '—' }}</td>
                  <td>
                    <span :class="['status-pill', labelStatusBlog(item.status).kelas]">
                      {{ labelStatusBlog(item.status).teks }}
                    </span>
                  </td>
                  <td>{{ formatTanggal(item.published_at || item.created_at) }}</td>
                  <td>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap">
                      <button
                        type="button"
                        class="button button-sm button-secondary"
                        @click="bukaTinjau(item)"
                      >
                        Buka
                      </button>
                      <button
                        v-if="item.status === 'draft'"
                        type="button"
                        class="button button-sm"
                        @click="publikasikanDraft(item)"
                      >
                        Terbitkan
                      </button>
                      <button
                        v-if="item.status === 'published'"
                        type="button"
                        class="button button-sm button-secondary"
                        style="color: #69766e"
                        title="Sembunyikan dari publik"
                        @click="arsipkanArtikel(item)"
                      >
                        Arsipkan
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>

      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <!-- TAB 3: LOG AUDIT AKTIVITAS SISTEM                                    -->
      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <section v-else-if="activeTab === 'logs'">
        <section class="card">
          <div class="section-heading">
            <div>
              <span class="eyebrow">Audit Trail</span>
              <h2>Log Aktivitas Petugas & Mahasiswa</h2>
            </div>
            <button class="button button-secondary" type="button" :disabled="sedangMemuatLogs" @click="muatLogs">
              {{ sedangMemuatLogs ? 'Menyinkronkan...' : '↻ Segarkan Log' }}
            </button>
          </div>

          <p class="muted">
            Mencatat setiap pembaruan status pengajuan, permintaan revisi dokumen, aksi moderasi blog, dan ekspor data secara permanen.
          </p>

          <div v-if="sedangMemuatLogs" class="muted" style="padding: 24px 0">Mengambil data log audit...</div>
          <div v-else-if="logs.length === 0" class="muted" style="padding: 24px 0">Belum ada aktivitas tercatat.</div>
          <div v-else class="table-wrap" style="margin-top: 16px">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Pengguna</th>
                  <th>Tindakan</th>
                  <th>Deskripsi Aktivitas</th>
                  <th>IP Address</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="log in logs" :key="log.id">
                  <td style="white-space: nowrap">
                    {{ new Date(log.created_at).toLocaleString('id-ID') }}
                  </td>
                  <td>
                    <strong>{{ log.user?.name || 'Sistem' }}</strong>
                    <div class="table-meta">{{ log.role || 'system' }}</div>
                  </td>
                  <td>
                    <span class="badge-tag" style="background: #f1f5f9; color: #475569">
                      {{ log.action }}
                    </span>
                  </td>
                  <td>{{ log.description }}</td>
                  <td style="font-family: monospace; font-size: 12px">{{ log.ip_address || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>

      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <!-- MODAL: MINTA REVISI DOKUMEN PENGAJUAN                                -->
      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <div v-if="isModalMintaRevisiOpen && pengajuanDipilih" class="modal-backdrop" @click.self="tutupModalMintaRevisi">
        <div class="modal-card modal-card--sm">
          <button type="button" class="btn-close" @click="tutupModalMintaRevisi">&times;</button>
          <h2 style="font-size: 20px; color: #b45309; margin-top: 0">Permintaan Perbaikan Dokumen</h2>
          <p class="muted" style="font-size: 14px">
            Pengajuan: <strong>{{ pengajuanDipilih.nomor_pengajuan }}</strong> ({{ pengajuanDipilih.nama_mahasiswa }}).
            Berikan catatan spesifik mengapa dokumen perlu diperbaiki:
          </p>

          <div class="field">
            <label for="catatan-revisi-field">Alasan / Instruksi Perbaikan *</label>
            <textarea
              id="catatan-revisi-field"
              v-model="catatanRevisiText"
              rows="4"
              placeholder="Contoh: Berkas formulir belum ditandatangani oleh Dosen Pembimbing Akademik, mohon unggah ulang versi yang sudah dilegalisir..."
              required
            />
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px">
            <button type="button" class="button button-secondary" @click="tutupModalMintaRevisi">
              Batal
            </button>
            <button
              type="button"
              class="button"
              style="background: #b45309; border-color: #b45309; color: #fff"
              :disabled="sedangKirimRevisi || !catatanRevisiText.trim()"
              @click="kirimPermintaanRevisi"
            >
              {{ sedangKirimRevisi ? 'Mengirim...' : 'Kirim Permintaan Revisi' }}
            </button>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <!-- MODAL: PEMERIKSAAN & DETAIL REVIEW ARTIKEL                          -->
      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <div v-if="isReviewModalOpen && selectedPost" class="modal-backdrop" @click.self="tutupModalTinjau">
        <div class="modal-card">
          <button type="button" class="btn-close" @click="tutupModalTinjau">&times;</button>

          <div class="modal-header">
            <span
              class="category-pill"
              :style="{
                backgroundColor: (selectedPost.category?.badge_color || '#1e5948') + '18',
                color: selectedPost.category?.badge_color || '#1e5948',
              }"
            >
              {{ selectedPost.category?.name || 'Umum' }}
            </span>
            <span :class="['status-pill', labelStatusBlog(selectedPost.status).kelas]">
              {{ labelStatusBlog(selectedPost.status).teks }}
            </span>
          </div>

          <h2 class="modal-title">{{ selectedPost.title }}</h2>

          <div class="modal-meta-row">
            <div>
              <strong>Penulis:</strong> {{ selectedPost.author?.name }} (Role: {{ selectedPost.author?.role || 'mahasiswa' }})
            </div>
            <div>
              <strong>Tanggal:</strong> {{ formatTanggal(selectedPost.created_at) }}
            </div>
          </div>

          <!-- Cover Image jika ada -->
          <div v-if="selectedPost.cover_image_path" class="modal-cover-wrap">
            <img
              :src="selectedPost.cover_image_path.startsWith('http') ? selectedPost.cover_image_path : `${apiUrl.replace('/api', '')}/storage/${selectedPost.cover_image_path}`"
              :alt="selectedPost.title"
              class="modal-cover-img"
            />
          </div>

          <!-- Ringkasan -->
          <div class="summary-box" style="margin: 16px 0">
            <strong>Ringkasan:</strong>
            <p>{{ selectedPost.summary }}</p>
          </div>

          <!-- Isi Konten Artikel Lengkap -->
          <div class="modal-prose-wrap">
            <div class="prose-label">Isi Naskah:</div>
            <div class="modal-prose" v-html="selectedPost.content" />
          </div>

          <!-- Catatan / Riwayat penolakan sebelumnya jika ada -->
          <div v-if="selectedPost.rejection_reason" class="rejection-box" style="margin-top: 16px">
            <strong>Catatan Penolakan Saat Ini:</strong>
            <p>{{ selectedPost.rejection_reason }}</p>
          </div>

          <!-- Action bar moderasi -->
          <div class="modal-actions-bar">
            <template v-if="selectedPost.status === 'pending'">
              <div class="approve-note-field">
                <input
                  v-model="approveNote"
                  type="text"
                  placeholder="Catatan persetujuan opsional (misal: 'Disetujui untuk warta kampus')..."
                />
              </div>
              <button
                type="button"
                class="button button-danger"
                :disabled="sedangAksiModerasi"
                @click="bukaDialogTolak(selectedPost)"
              >
                ✕ Tolak Artikel
              </button>
              <button
                type="button"
                class="button"
                :disabled="sedangAksiModerasi"
                @click="setujuiArtikel(selectedPost)"
              >
                {{ sedangAksiModerasi ? 'Memproses...' : '✓ Setujui & Publikasikan' }}
              </button>
            </template>
            <template v-else>
              <button type="button" class="button button-secondary" @click="tutupModalTinjau">
                Tutup
              </button>
            </template>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <!-- MODAL: FORM ALASAN PENOLAKAN WAJIB                                  -->
      <!-- ═══════════════════════════════════════════════════════════════════ -->
      <div v-if="isRejectModalOpen && selectedPost" class="modal-backdrop" @click.self="tutupDialogTolak">
        <div class="modal-card modal-card--sm">
          <button type="button" class="btn-close" @click="tutupDialogTolak">&times;</button>
          <h2 style="font-size: 20px; color: #8f2b18; margin-top: 0">Tolak Naskah Artikel</h2>
          <p class="muted" style="font-size: 14px">
            Mohon berikan alasan penolakan dan panduan perbaikan yang jelas kepada mahasiswa penulis:
            <strong>{{ selectedPost.author?.name }}</strong>.
          </p>

          <div class="field">
            <label for="reject-textarea">Alasan Penolakan & Catatan Revisi *</label>
            <textarea
              id="reject-textarea"
              v-model="rejectReason"
              rows="4"
              placeholder="Contoh: Format penulisan belum memenuhi standar akademik, mohon sertakan referensi tautan resmi pada paragraf kedua..."
              required
            />
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px">
            <button type="button" class="button button-secondary" @click="tutupDialogTolak">
              Batal
            </button>
            <button
              type="button"
              class="button button-danger"
              :disabled="sedangAksiModerasi"
              @click="konfirmasiTolak"
            >
              {{ sedangAksiModerasi ? 'Menyimpan...' : 'Konfirmasi Tolak Artikel' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.status-waiting { background:#f7efd9; color:#86651f; }
.status-progress { background:#e2edf1; color:#285c6c; }
.status-done { background:#e1eee1; color:#2d6946; }
.status-rejected { background:#f5e5df; color:#923f2c; }

.heading-actions {
  display: flex;
  gap: 10px;
}

/* Nav Tabs */
.admin-nav-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 2px solid var(--line);
  margin-bottom: 24px;
}
.nav-tab-btn {
  background: transparent;
  border: none;
  padding: 12px 20px;
  font-size: 15px;
  font-weight: 700;
  color: var(--muted);
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 8px;
}
.nav-tab-btn:hover {
  color: var(--green);
}
.nav-tab-btn.active {
  color: var(--green-dark);
  border-bottom-color: var(--green);
}
.tab-badge-pending {
  background: #c05621;
  color: white;
  font-size: 11px;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 999px;
}

/* Blog Subtabs */
.blog-subtabs {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}
.subtab-btn {
  background: #edf2ea;
  border: 1px solid var(--line);
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #3b5043;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}
.subtab-btn.active {
  background: var(--green);
  color: #fffef9;
  border-color: var(--green);
}
.subtab-badge {
  background: #b83a22;
  color: white;
  padding: 1px 6px;
  border-radius: 999px;
  font-size: 11px;
}

.category-pill {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
}
.status-pill {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
}
.badge-draft { background: #ebeee8; color: #556358; }
.badge-pending { background: #fcf1d8; color: #8c6819; }
.badge-published { background: #e1eee1; color: #276241; }
.badge-rejected { background: #f8e5e0; color: #923f2c; }

.button-sm {
  min-height: 34px;
  padding: 6px 12px;
  font-size: 12px;
}
.button-danger {
  background: #923f2c;
  color: white;
  border-color: #923f2c;
}
.button-danger:hover {
  background: #732f20;
}

.empty-box {
  padding: 48px 16px;
  text-align: center;
  border: 1px dashed var(--line);
  border-radius: 12px;
}

/* Modal Styling */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(18, 38, 30, 0.65);
  backdrop-filter: blur(4px);
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.modal-card {
  position: relative;
  background: var(--paper);
  border-radius: 16px;
  width: min(840px, 100%);
  max-height: 90vh;
  overflow-y: auto;
  padding: 32px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
}
.modal-card--sm {
  width: min(520px, 100%);
}
.btn-close {
  position: absolute;
  top: 18px;
  right: 20px;
  background: transparent;
  border: none;
  font-size: 26px;
  cursor: pointer;
  color: var(--muted);
}
.modal-header {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 12px;
}
.modal-title {
  font-size: 24px;
  margin: 0 0 12px;
  color: var(--green-dark);
}
.modal-meta-row {
  display: flex;
  justify-content: space-between;
  border-bottom: 1px solid var(--line);
  padding-bottom: 14px;
  font-size: 13px;
  color: var(--muted);
  flex-wrap: wrap;
  gap: 10px;
}
.modal-cover-wrap {
  width: 100%;
  max-height: 240px;
  overflow: hidden;
  border-radius: 8px;
  margin: 16px 0;
}
.modal-cover-img {
  width: 100%;
  max-height: 240px;
  object-fit: cover;
}
.summary-box {
  background: #f4f8f3;
  border-left: 4px solid var(--green);
  padding: 12px 16px;
  border-radius: 0 6px 6px 0;
  font-size: 14px;
}
.summary-box strong { color: var(--green-dark); }
.summary-box p { margin: 4px 0 0; }
.modal-prose-wrap {
  margin: 20px 0;
}
.prose-label {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted);
  margin-bottom: 8px;
}
.modal-prose {
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 20px;
  background: #fff;
  max-height: 320px;
  overflow-y: auto;
  line-height: 1.7;
  font-size: 14px;
}
.modal-prose :deep(img) {
  max-width: 100%;
  height: auto;
}
.rejection-box {
  background: #fdf2ef;
  border-left: 4px solid #923f2c;
  padding: 12px 16px;
  border-radius: 0 6px 6px 0;
  font-size: 13px;
}
.rejection-box p { margin: 4px 0 0; color: #6e2a1b; }
.modal-actions-bar {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 12px;
  border-top: 1px solid var(--line);
  padding-top: 20px;
  margin-top: 24px;
  flex-wrap: wrap;
}
.approve-note-field {
  flex: 1;
  min-width: 220px;
}
.approve-note-field input {
  width: 100%;
  font-size: 13px;
  padding: 8px 12px;
  min-height: 38px;
}
</style>
