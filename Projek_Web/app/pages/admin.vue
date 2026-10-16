<script setup lang="ts">
const config = useRuntimeConfig()
const apiUrl = config.public.apiBase

interface Pengajuan {
  id: number
  nomor_pengajuan: string
  nama_mahasiswa: string
  nim: string
  email: string
  status: string
  created_at: string
  layanan?: { nama: string }
}

const pengajuan = ref<Pengajuan[]>([])
const pencarian = ref('')
const filterStatus = ref('Semua')
const sedangMemuat = ref(false)
const pesan = ref('')
const kesalahan = ref('')
const sedangMemperbarui = ref<string | null>(null)

const pilihanStatus = ['Menunggu', 'Diproses', 'Selesai', 'Ditolak']

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

function kelasStatus(status: string) {
  return {
    'Menunggu': 'status status-waiting',
    'Diproses': 'status status-progress',
    'Selesai': 'status status-done',
    'Ditolak': 'status status-rejected',
  }[status] || 'status'
}

async function muatPengajuan() {
  sedangMemuat.value = true
  kesalahan.value = ''
  try {
    pengajuan.value = await $fetch<Pengajuan[]>(`${apiUrl}/pengajuan`)
  } catch {
    kesalahan.value = 'Gagal mengambil data. Pastikan server Laravel berjalan.'
  } finally {
    sedangMemuat.value = false
  }
}

async function ubahStatus(item: Pengajuan, status: string) {
  if (status === item.status) return
  const statusLama = item.status
  pesan.value = ''
  kesalahan.value = ''
  sedangMemperbarui.value = item.nomor_pengajuan
  try {
    await $fetch(`${apiUrl}/pengajuan/${encodeURIComponent(item.nomor_pengajuan)}/status`, {
      method: 'PATCH',
      body: { status },
    })
    item.status = status
    pesan.value = `Status ${item.nomor_pengajuan} berhasil diubah menjadi ${status}.`
  } catch (error: any) {
    item.status = statusLama
    kesalahan.value = error.data?.message || 'Gagal memperbarui status pengajuan.'
  } finally {
    sedangMemperbarui.value = null
  }
}

onMounted(muatPengajuan)
</script>

<template>
  <main class="section admin-page">
    <div class="container">
      <div class="admin-heading">
        <div>
          <span class="eyebrow">Ruang kerja petugas</span>
          <h1>Ringkasan pengajuan.</h1>
          <p>Pantau permohonan mahasiswa dan perbarui status dari satu tempat.</p>
        </div>
        <button class="button button-secondary" type="button" :disabled="sedangMemuat" @click="muatPengajuan">
          {{ sedangMemuat ? 'Memuat data...' : '↻ Muat ulang data' }}
        </button>
      </div>

      <div class="admin-statistics">
        <article class="stat-card">
          <p class="stat-label">Total pengajuan</p>
          <p class="stat-value">{{ statistik.total }}</p>
        </article>
        <article class="stat-card stat-card--waiting">
          <p class="stat-label">Menunggu</p>
          <p class="stat-value">{{ statistik.menunggu }}</p>
        </article>
        <article class="stat-card stat-card--progress">
          <p class="stat-label">Diproses</p>
          <p class="stat-value">{{ statistik.diproses }}</p>
        </article>
        <article class="stat-card stat-card--done">
          <p class="stat-label">Selesai</p>
          <p class="stat-value">{{ statistik.selesai }}</p>
        </article>
        <article class="stat-card stat-card--rejected">
          <p class="stat-label">Ditolak</p>
          <p class="stat-value">{{ statistik.ditolak }}</p>
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
          <span class="muted">{{ daftarPengajuan.length }} data ditampilkan</span>
        </div>

        <div class="admin-tools">
          <div class="field">
            <label for="cari">Cari pengajuan</label>
            <input id="cari" v-model="pencarian" type="search" placeholder="Nama, NIM, nomor, atau layanan" />
          </div>
          <div class="field">
            <label for="filter">Status pengajuan</label>
            <select id="filter" v-model="filterStatus">
              <option>Semua</option>
              <option v-for="status in pilihanStatus" :key="status">{{ status }}</option>
            </select>
          </div>
          <button class="button" type="button" :disabled="sedangMemuat" @click="muatPengajuan">
            Terapkan / Segarkan
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
                <th>Layanan</th>
                <th>Tanggal masuk</th>
                <th>Status saat ini</th>
                <th>Perbarui status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in daftarPengajuan" :key="item.id">
                <td>
                  <div class="student-name">{{ item.nama_mahasiswa }}</div>
                  <span class="table-meta">NIM {{ item.nim }}</span>
                  <span class="table-meta">{{ item.nomor_pengajuan }}</span>
                </td>
                <td>{{ item.layanan?.nama || '—' }}</td>
                <td>{{ new Date(item.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) }}</td>
                <td><AppBadge :status="item.status" /></td>
                <td>
                  <select
                    class="table-status"
                    :value="item.status"
                    :disabled="sedangMemperbarui === item.nomor_pengajuan"
                    :aria-label="`Ubah status ${item.nomor_pengajuan}`"
                    @change="ubahStatus(item, ($event.target as HTMLSelectElement).value)"
                  >
                    <option v-for="status in pilihanStatus" :key="status">{{ status }}</option>
                  </select>
                  <span v-if="sedangMemperbarui === item.nomor_pengajuan" class="table-meta">Menyimpan...</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <p class="muted" style="font-size:12px; margin-top:16px">Dashboard prototipe · Pastikan akses petugas dilindungi autentikasi sebelum aplikasi dipublikasikan.</p>
    </div>
  </main>
</template>

<style scoped>
.status-waiting { background:#f7efd9; color:#86651f; }
.status-progress { background:#e2edf1; color:#285c6c; }
.status-done { background:#e1eee1; color:#2d6946; }
.status-rejected { background:#f5e5df; color:#923f2c; }
</style>
