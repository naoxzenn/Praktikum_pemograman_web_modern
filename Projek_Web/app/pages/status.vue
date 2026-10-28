<script setup lang="ts">
definePageMeta({
  middleware: ['role'],
})

const route = useRoute()
const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const { authHeaders } = useAuth()

interface HistoryItem {
  id: number
  status_sebelumnya: string | null
  status_baru: string
  catatan: string | null
  created_at: string
  user?: { name: string; role: string }
}

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
  layanan?: {
    id: number
    nama: string
    deskripsi?: string
    sla_hari_kerja?: number
  }
  histories?: HistoryItem[]
}

const nomor = ref(String(route.query.nomor || ''))
const pengajuan = ref<Pengajuan | null>(null)
const sedangMencari = ref(false)
const kesalahan = ref('')

// Form revisi
const fileRevisi = ref<File | null>(null)
const ketRevisi = ref('')
const sedangRevisi = ref(false)
const pesanSuksesRevisi = ref('')
const kesalahanRevisi = ref('')

async function cariPengajuan() {
  kesalahan.value = ''
  pesanSuksesRevisi.value = ''
  kesalahanRevisi.value = ''
  pengajuan.value = null

  if (!nomor.value.trim()) {
    kesalahan.value = 'Masukkan nomor pelacakan terlebih dahulu.'
    return
  }

  sedangMencari.value = true

  try {
    pengajuan.value = await $fetch<Pengajuan>(
      `${apiUrl}/pengajuan/${encodeURIComponent(nomor.value.trim())}`,
      {
        headers: authHeaders(),
      }
    )
  } catch (error: any) {
    if (error.statusCode === 401 || error.status === 401) {
      await navigateTo('/login')
      return
    }
    if (error.statusCode === 404 || error.status === 404) {
      kesalahan.value = 'Nomor pengajuan tidak ditemukan atau bukan milik akun Anda.'
    } else {
      kesalahan.value =
        error.data?.message ||
        'Gagal mengambil data. Pastikan server Laravel berjalan.'
    }
  } finally {
    sedangMencari.value = false
  }
}

function handleRevisiFileChange(event: Event) {
  kesalahanRevisi.value = ''
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) {
    fileRevisi.value = null
    return
  }
  if (file.size > 5 * 1024 * 1024) {
    kesalahanRevisi.value = 'Ukuran berkas melebihi batas 5MB.'
    fileRevisi.value = null
    target.value = ''
    return
  }
  fileRevisi.value = file
}

async function kirimRevisi() {
  if (!fileRevisi.value) {
    kesalahanRevisi.value = 'Silakan pilih berkas dokumen perbaikan.'
    return
  }

  sedangRevisi.value = true
  kesalahanRevisi.value = ''
  pesanSuksesRevisi.value = ''

  try {
    const formData = new FormData()
    formData.append('dokumen', fileRevisi.value)
    if (ketRevisi.value.trim()) {
      formData.append('keterangan', ketRevisi.value.trim())
    }

    const res = await $fetch<{ message: string; data: Pengajuan }>(
      `${apiUrl}/pengajuan/${encodeURIComponent(pengajuan.value!.nomor_pengajuan)}/revisi`,
      {
        method: 'POST',
        body: formData,
        headers: authHeaders(),
      }
    )

    pengajuan.value = res.data
    pesanSuksesRevisi.value = res.message || 'Dokumen perbaikan berhasil dikirimkan.'
    fileRevisi.value = null
    ketRevisi.value = ''
  } catch (error: any) {
    kesalahanRevisi.value = error.data?.message || 'Gagal mengirimkan berkas revisi.'
  } finally {
    sedangRevisi.value = false
  }
}

async function unduhDokumen() {
  if (!pengajuan.value) return
  try {
    const blob = await $fetch<Blob>(
      `${apiUrl}/pengajuan/${encodeURIComponent(pengajuan.value.nomor_pengajuan)}/dokumen`,
      {
        headers: authHeaders(),
        responseType: 'blob',
      }
    )
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = pengajuan.value.dokumen_nama_asli || 'dokumen_lampiran.pdf'
    document.body.appendChild(a)
    a.click()
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)
  } catch (err: any) {
    alert(err.data?.message || 'Gagal mengunduh dokumen lampiran.')
  }
}

function cetakBukti() {
  window.print()
}

const isTerlambat = computed(() => {
  if (!pengajuan.value?.estimasi_selesai) return false
  if (['Selesai', 'Ditolak'].includes(pengajuan.value.status)) return false
  const deadline = new Date(pengajuan.value.estimasi_selesai)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return deadline < today
})

onMounted(() => {
  if (nomor.value.trim()) {
    cariPengajuan()
  }
})
</script>

<template>
  <main class="section">
    <div class="container">
      <div class="no-print">
        <span class="eyebrow">Pelacakan pengajuan</span>
        <h1>Pantau Status Layanan Mahasiswa</h1>
        <p class="muted">
          Masukkan nomor pelacakan untuk memantau status berkas, estimasi SLA, dan riwayat proses.
        </p>

        <form class="card" @submit.prevent="cariPengajuan">
          <div class="field">
            <label for="nomor">Nomor pelacakan</label>
            <input
              id="nomor"
              v-model="nomor"
              placeholder="Contoh: PM-20261028-ABC123"
              required
            />
          </div>

          <AppAlert
            v-if="kesalahan"
            type="error"
            :message="kesalahan"
          />

          <button
            class="button"
            type="submit"
            :disabled="sedangMencari"
          >
            {{ sedangMencari ? 'Mencari...' : 'Cari Pengajuan' }}
          </button>
        </form>
      </div>

      <!-- Hasil Pengajuan Lengkap -->
      <section
        v-if="pengajuan"
        class="card print-card"
        style="margin-top: 24px"
        aria-live="polite"
      >
        <div class="card-header-bar">
          <div>
            <span class="badge-tag">{{ pengajuan.layanan?.nama || 'Layanan Kampus' }}</span>
            <h2 style="margin: 8px 0 4px">Pengajuan: {{ pengajuan.nomor_pengajuan }}</h2>
            <p class="muted" style="margin: 0">Diajukan pada {{ new Date(pengajuan.created_at).toLocaleString('id-ID') }}</p>
          </div>
          <div class="no-print action-buttons">
            <button class="button button-secondary" type="button" @click="cetakBukti">
              🖨️ Cetak Bukti
            </button>
            <button v-if="pengajuan.dokumen_path" class="button button-secondary" type="button" @click="unduhDokumen">
              📄 Unduh Lampiran
            </button>
          </div>
        </div>

        <hr style="margin: 16px 0; border: none; border-top: 1px solid #e5e7eb" />

        <!-- Data Pemohon -->
        <div class="grid-info">
          <div>
            <p class="muted" style="margin: 0">Nama Mahasiswa</p>
            <strong style="font-size: 16px">{{ pengajuan.nama_mahasiswa }}</strong>
          </div>
          <div>
            <p class="muted" style="margin: 0">NIM</p>
            <strong style="font-size: 16px">{{ pengajuan.nim }}</strong>
          </div>
          <div>
            <p class="muted" style="margin: 0">Email</p>
            <strong style="font-size: 16px">{{ pengajuan.email }}</strong>
          </div>
          <div>
            <p class="muted" style="margin: 0">Status Saat Ini</p>
            <div style="margin-top: 4px">
              <AppBadge :status="pengajuan.status" />
            </div>
          </div>
        </div>

        <!-- SLA Estimasi Waktu Layanan -->
        <div class="sla-box" style="margin-top: 20px">
          <div>
            <strong>Estimasi Penyelesaian (SLA):</strong>
            <span v-if="pengajuan.estimasi_selesai" style="margin-left: 8px">
              {{ new Date(pengajuan.estimasi_selesai).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
            </span>
            <span v-else style="margin-left: 8px; color: #6b7280">-</span>
          </div>
          <div>
            <span v-if="isTerlambat" class="badge-terlambat">⚠️ Melewati Estimasi SLA</span>
            <span v-else-if="pengajuan.status === 'Selesai'" class="badge-selesai">✅ Selesai</span>
            <span v-else class="badge-on-track">⏱️ Dalam Proses Sesuai Jadwal</span>
          </div>
        </div>

        <!-- Callout Permintaan Revisi jika Perlu Perbaikan -->
        <div v-if="pengajuan.status === 'Perlu Perbaikan'" class="revision-card no-print" style="margin-top: 20px">
          <h3 style="color: #b45309; margin-top: 0">⚠️ Permintaan Perbaikan Dokumen</h3>
          <p><strong>Catatan Petugas:</strong> {{ pengajuan.catatan_revisi || 'Silakan lengkapi atau perbaiki dokumen pengajuan Anda.' }}</p>

          <form @submit.prevent="kirimRevisi" style="margin-top: 14px">
            <div class="field">
              <label for="fileRev">Unggah Berkas Pengganti (PDF/JPG/PNG maks. 5MB)</label>
              <input id="fileRev" type="file" accept=".pdf,.jpg,.jpeg,.png" required @change="handleRevisiFileChange" />
            </div>
            <div class="field" style="margin-top: 10px">
              <label for="ketRev">Keterangan Tambahan Revisi (opsional)</label>
              <textarea id="ketRev" v-model="ketRevisi" rows="2" placeholder="Jelaskan perubahan yang telah Anda buat..."></textarea>
            </div>

            <AppAlert v-if="kesalahanRevisi" type="error" :message="kesalahanRevisi" style="margin-top: 10px" />
            <AppAlert v-if="pesanSuksesRevisi" type="success" :message="pesanSuksesRevisi" style="margin-top: 10px" />

            <button class="button" type="submit" :disabled="sedangRevisi" style="margin-top: 12px">
              {{ sedangRevisi ? 'Mengirim Revisi...' : 'Kirim Berkas Perbaikan' }}
            </button>
          </form>
        </div>

        <!-- Timeline Status Pengajuan -->
        <div style="margin-top: 28px">
          <h3 style="margin-bottom: 16px">Riwayat & Timeline Proses</h3>
          <div v-if="pengajuan.histories?.length" class="timeline">
            <div v-for="(h, idx) in pengajuan.histories" :key="h.id" class="timeline-item">
              <div class="timeline-dot"></div>
              <div class="timeline-content">
                <div class="timeline-meta">
                  <strong>{{ h.status_baru }}</strong>
                  <span class="muted" style="font-size: 13px">
                    {{ new Date(h.created_at).toLocaleString('id-ID') }}
                  </span>
                </div>
                <p v-if="h.catatan" class="timeline-catatan">{{ h.catatan }}</p>
                <small v-if="h.user" class="muted">Oleh: {{ h.user.name }} ({{ h.user.role }})</small>
              </div>
            </div>
          </div>
          <p v-else class="muted">Belum ada riwayat tercatat.</p>
        </div>

      </section>
    </div>
  </main>
</template>

<style scoped>
.card-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}
.action-buttons {
  display: flex;
  gap: 8px;
}
.badge-tag {
  background: #e6f4ea;
  color: #137333;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}
.grid-info {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}
.sla-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 14px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}
.badge-terlambat {
  background: #fef2f2;
  color: #b91c1c;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}
.badge-on-track {
  background: #f0fdf4;
  color: #15803d;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}
.badge-selesai {
  background: #eff6ff;
  color: #1d4ed8;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}
.revision-card {
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  padding: 16px;
}
.timeline {
  position: relative;
  border-left: 2px solid #e5e7eb;
  padding-left: 18px;
  margin-left: 8px;
}
.timeline-item {
  position: relative;
  margin-bottom: 20px;
}
.timeline-dot {
  position: absolute;
  left: -24px;
  top: 4px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #10b981;
  border: 2px solid #fff;
}
.timeline-meta {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}
.timeline-catatan {
  margin: 4px 0 2px;
  color: #374151;
  font-size: 14px;
}

@media print {
  .no-print {
    display: none !important;
  }
  .print-card {
    border: 1px solid #000 !important;
    box-shadow: none !important;
  }
}
</style>
