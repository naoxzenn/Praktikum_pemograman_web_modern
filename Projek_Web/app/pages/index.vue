<script setup lang="ts">
const layanan = [
  {
    id: 1,
    nama: 'Surat Aktif Kuliah',
    deskripsi: 'Ajukan surat keterangan bahwa kamu masih aktif sebagai mahasiswa.'
  },
  {
    id: 2,
    nama: 'Pengantar Penelitian',
    deskripsi: 'Ajukan surat pengantar untuk kebutuhan penelitian.'
  },
  {
    id: 3,
    nama: 'Legalisasi Dokumen',
    deskripsi: 'Ajukan legalisasi dokumen akademik.'
  }
]
</script>

<template>
  <main>
    <section class="hero">
      <div class="container">
        <span class="eyebrow">LAYANAN ADMINISTRASI KAMPUS</span>
        <h1>Urus kebutuhan akademik dengan lebih mudah.</h1>
        <p>
          Temukan layanan, pahami persyaratan, dan pantau pengajuan
          dalam satu portal.
        </p>
        <NuxtLink to="/layanan" class="button">
          Jelajahi Layanan
        </NuxtLink>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="section-heading">
          <div>
            <span class="eyebrow">LAYANAN TERSEDIA</span>
            <h2>Apa yang kamu butuhkan?</h2>
          </div>
          <NuxtLink to="/layanan">Lihat semua layanan →</NuxtLink>
        </div>

        
        <div class="grid">
          <ServiceCard
            v-for="item in layanan"
            :key="item.id"
            :nama="item.nama"
            :deskripsi="item.deskripsi"
          >
            <NuxtLink
              :to="`/layanan?id=${item.id}`"
              class="button button-secondary"
            >
              Lihat persyaratan
            </NuxtLink>
          </ServiceCard>
        </div>

      </div>
    </section>

    <section class="section">
      <div class="container card">
        <h2>Sudah mengajukan permohonan?</h2>
        <p>
          Periksa status pengajuan menggunakan nomor pelacakan.
        </p>
        <NuxtLink to="/status" class="button">
          Lacak Pengajuan
        </NuxtLink>
      </div>
    </section>
  </main>
</template>