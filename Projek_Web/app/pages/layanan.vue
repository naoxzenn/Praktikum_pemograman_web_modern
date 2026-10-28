
<script setup lang="ts">
interface Layanan {
  id: number
  nama: string
  deskripsi: string | null
}

const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const kataKunci = ref('')
const route = useRoute()

const {
  data: layanan,
  pending,
  error,
  refresh
} = await useFetch<Layanan[]>(
  `${apiUrl}/layanan`
)

const daftarLayanan = computed(() => layanan.value ?? [])

const hasil = computed(() => {
  const kata = kataKunci.value.trim().toLowerCase()

  return daftarLayanan.value.filter(item =>
    item.nama.toLowerCase().includes(kata)
  )
})

const idTerpilih = computed(() => {
  const id = Number(route.query.id)
  return Number.isInteger(id) && id > 0 ? id : null
})

const detail = computed(() =>
  idTerpilih.value === null
    ? undefined
    : daftarLayanan.value.find(
        item => item.id === idTerpilih.value
      )
)
</script>

<template>
  <main class="section">
    <div class="container">
      <span class="eyebrow">Katalog layanan</span>
      <h1>Daftar Layanan Mahasiswa</h1>
      <p class="muted">
        Pilih layanan dan baca persyaratannya sebelum mengajukan.
      </p>

      <p v-if="pending" role="status">
        Memuat daftar layanan...
      </p>

      <div v-else-if="error" role="alert">
        <p>Gagal mengambil data dari server Laravel.</p>
        <p>Pastikan server Laravel berjalan di port 8000.</p>
        <button class="button" @click="refresh()">
          Coba Lagi
        </button>
      </div>

      <template v-else-if="detail">
        <NuxtLink to="/layanan">
          ← Kembali ke layanan
        </NuxtLink>

        <article class="card" style="margin-top: 24px">
          <span class="eyebrow">Detail layanan</span>
          <h2>{{ detail.nama }}</h2>
          <p>{{ detail.deskripsi || 'Belum ada deskripsi.' }}</p>

          <p class="muted">
            Informasi persyaratan belum tersedia dari API.
          </p>

          <NuxtLink
            :to="`/pengajuan?layanan=${detail.id}`"
            class="button"
          >
            Ajukan Layanan
          </NuxtLink>
        </article>
      </template>

      <template v-else>
        <div class="field">
          <label for="cari">Cari nama layanan</label>
          <input
            id="cari"
            v-model="kataKunci"
            type="search"
            placeholder="Contoh: surat aktif kuliah"
          />
        </div>

        <p>Jumlah hasil: {{ hasil.length }}</p>

        <div v-if="hasil.length" class="grid">
          <article
            v-for="item in hasil"
            :key="item.id"
            class="card"
          >
            <h2>{{ item.nama }}</h2>
            <p>{{ item.deskripsi || 'Belum ada deskripsi.' }}</p>

            <NuxtLink
              :to="`/layanan?id=${item.id}`"
              class="button button-secondary"
            >
              Baca Detail
            </NuxtLink>
          </article>
        </div>

        <p v-else role="status">
          Layanan tidak ditemukan. Coba kata kunci lain.
        </p>
      </template>
    </div>
  </main>
</template>
