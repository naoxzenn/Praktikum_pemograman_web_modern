
<script setup lang="ts">
const route = useRoute()
const config = useRuntimeConfig()
const apiUrl = config.public.apiBase

interface Pengajuan {
  nomor_pengajuan: string
  nama_mahasiswa: string
  nim: string
  email: string
  status: string
  created_at: string
  layanan?: {
    id: number
    nama: string
    deskripsi?: string
  }
}

const nomor = ref(String(route.query.nomor || ''))
const pengajuan = ref<Pengajuan | null>(null)
const sedangMencari = ref(false)
const kesalahan = ref('')

async function cariPengajuan() {
  kesalahan.value = ''
  pengajuan.value = null

  if (!nomor.value.trim()) {
    kesalahan.value = 'Masukkan nomor pelacakan terlebih dahulu.'
    return
  }

  sedangMencari.value = true

  try {
    pengajuan.value = await $fetch<Pengajuan>(
      `${apiUrl}/pengajuan/${encodeURIComponent(nomor.value.trim())}`
    )
  } catch (error: any) {
    if (error.statusCode === 404) {
      kesalahan.value = 'Nomor pengajuan tidak ditemukan.'
    } else {
      kesalahan.value =
        error.data?.message ||
        'Gagal mengambil data. Pastikan server Laravel berjalan.'
    }
  } finally {
    sedangMencari.value = false
  }
}

onMounted(() => {
  if (nomor.value.trim()) {
    cariPengajuan()
  }
})
</script>

<template>
  <main class="section">
    <div class="container">
      <span class="eyebrow">Pelacakan pengajuan</span>
      <h1>Pantau Status Pengajuan</h1>
      <p class="muted">
        Masukkan nomor pelacakan untuk melihat status pengajuan kamu.
      </p>

      <form class="card" @submit.prevent="cariPengajuan">
        <div class="field">
          <label for="nomor">Nomor pelacakan</label>
          <input
            id="nomor"
            v-model="nomor"
            placeholder="Contoh: PM-20261016-ABC123"
            required
          />
        </div>

        <p v-if="kesalahan" role="alert">
          {{ kesalahan }}
        </p>

        <button
          class="button"
          type="submit"
          :disabled="sedangMencari"
        >
          {{ sedangMencari ? 'Mencari...' : 'Cari Pengajuan' }}
        </button>
      </form>

      <section
        v-if="pengajuan"
        class="card"
        style="margin-top: 24px"
        aria-live="polite"
      >
        <h2>Hasil Pengajuan</h2>

        <p>
          <strong>Nomor:</strong>
          {{ pengajuan.nomor_pengajuan }}
        </p>

        <p>
          <strong>Nama mahasiswa:</strong>
          {{ pengajuan.nama_mahasiswa }}
        </p>

        <p>
          <strong>NIM:</strong>
          {{ pengajuan.nim }}
        </p>

        <p>
          <strong>Layanan:</strong>
          {{ pengajuan.layanan?.nama || 'Tidak tersedia' }}
        </p>

        <p>
          <strong>Tanggal pengajuan:</strong>
          {{ new Date(pengajuan.created_at).toLocaleString('id-ID') }}
        </p>

        <span class="status">
          {{ pengajuan.status }}
        </span>

        <p class="muted">
          Status ini diambil dari database pengajuan.
        </p>
      </section>
    </div>
  </main>
</template>
