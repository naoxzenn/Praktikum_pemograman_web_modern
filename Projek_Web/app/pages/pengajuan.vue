
<script setup lang="ts">
const route = useRoute()
const config = useRuntimeConfig()
const apiUrl = config.public.apiBase

interface Layanan {
  id: number
  nama: string
  deskripsi?: string
}

interface ResponsPengajuan {
  message: string
  data: {
    nomor_pengajuan: string
    status: string
    layanan?: Layanan
  }
}

const form = reactive({
  nama: '',
  nim: '',
  email: '',
  layananId: Number(route.query.layanan) || 0,
  keterangan: ''
})

const sudahDiperiksa = ref(false)
const nomorPengajuan = ref('')
const statusPengajuan = ref('')
const kesalahan = ref('')
const sedangMengirim = ref(false)

const {
  data: layanan,
  pending: memuatLayanan,
  error: errorLayanan
} = await useFetch<Layanan[]>(`${apiUrl}/layanan`)

const layananTerpilih = computed(() =>
  layanan.value?.find(item => item.id === form.layananId)
)

watch(layanan, (daftar) => {
  if (
    daftar?.length &&
    !daftar.some(item => item.id === form.layananId)
  ) {
    form.layananId = daftar[0].id
  }
}, { immediate: true })

function periksaFormulir() {
  kesalahan.value = ''

  if (!form.nama.trim() || !form.nim.trim() || !form.email.trim()) {
    kesalahan.value = 'Lengkapi nama, NIM, dan email.'
    return
  }

  if (!layananTerpilih.value) {
    kesalahan.value = 'Pilih layanan yang tersedia.'
    return
  }

  sudahDiperiksa.value = true
}

async function kirimPengajuan() {
  kesalahan.value = ''
  sedangMengirim.value = true

  try {
    const respons = await $fetch<ResponsPengajuan>(
      `${apiUrl}/pengajuan`,
      {
        method: 'POST',
        body: {
          layanan_id: form.layananId,
          nama_mahasiswa: form.nama.trim(),
          nim: form.nim.trim(),
          email: form.email.trim()
        }
      }
    )

    nomorPengajuan.value = respons.data.nomor_pengajuan
    statusPengajuan.value = respons.data.status
  } catch (error: any) {
    kesalahan.value =
      error.data?.message ||
      'Pengajuan gagal dikirim. Periksa koneksi ke server Laravel.'
  } finally {
    sedangMengirim.value = false
  }
}
</script>

<template>
  <main class="section">
    <div class="container">
      <span class="eyebrow">Formulir layanan</span>
      <h1>Pengajuan Layanan</h1>
      <p class="muted">
        Isi data mahasiswa, periksa ringkasan, lalu kirim pengajuan.
      </p>

      <div v-if="nomorPengajuan" class="card">
        <span class="status">Berhasil dikirim</span>
        <h2>Pengajuan berhasil dibuat</h2>
        <p>Nomor pelacakan kamu:</p>
        <h3>{{ nomorPengajuan }}</h3>
        <p>Status: {{ statusPengajuan }}</p>
        <NuxtLink
          :to="`/status?nomor=${encodeURIComponent(nomorPengajuan)}`"
          class="button"
        >
          Lihat Status
        </NuxtLink>
      </div>

      <form
        v-else
        class="card"
        @submit.prevent="periksaFormulir"
      >
        <p v-if="memuatLayanan">Memuat daftar layanan...</p>

        <p v-else-if="errorLayanan" role="alert">
          Daftar layanan gagal dimuat. Periksa server Laravel.
        </p>

        <template v-else>       
          <AppField
            v-model="form.nama"
            label="Nama mahasiswa"
            name="nama"
            placeholder="Masukkan nama lengkap"
            autocomplete="name"
            required
          />

          <AppField
            v-model="form.nim"
            label="NIM"
            name="nim"
            placeholder="Masukkan NIM"
            autocomplete="off"
            required
          />

          <AppField
            v-model="form.email"
            label="Email mahasiswa"
            name="email"
            type="email"
            placeholder="nama@email.com"
            autocomplete="email"
            required
          />


          <div class="field">
            <label for="layanan">Jenis layanan</label>
            <select
              id="layanan"
              v-model.number="form.layananId"
              required
            >
              <option
                v-for="item in layanan || []"
                :key="item.id"
                :value="item.id"
              >
                {{ item.nama }}
              </option>
            </select>
          </div>

          <div class="field">
            <label for="keterangan">
              Keterangan tambahan (opsional)
            </label>
            <textarea
              id="keterangan"
              v-model="form.keterangan"
            />
          </div>

          <AppAlert
            v-if="kesalahan"
            type="error"
            :message="kesalahan"
          />

          <AppButton
            type="button"
            variant="primary"
            @click="periksaFormulir"
          >
            Periksa Ringkasan
          </AppButton>

          <section
            v-if="sudahDiperiksa"
            class="card"
            style="margin-top: 24px"
            aria-labelledby="judul-ringkasan"
          >
            <h2 id="judul-ringkasan">Ringkasan Pengajuan</h2>
            <p><strong>Nama:</strong> {{ form.nama }}</p>
            <p><strong>NIM:</strong> {{ form.nim }}</p>
            <p><strong>Email:</strong> {{ form.email }}</p>
            <p>
              <strong>Layanan:</strong>
              {{ layananTerpilih?.nama }}
            </p>
            <p>
              <strong>Keterangan:</strong>
              {{ form.keterangan || 'Tidak ada' }}
            </p>

            <button
              type="button"
              class="button"
              :disabled="sedangMengirim"
              @click="kirimPengajuan"
            >
              {{ sedangMengirim ? 'Mengirim...' : 'Kirim Pengajuan' }}
            </button>
          </section>
        </template>
      </form>
    </div>
  </main>
</template>


.field {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 18px;
}

.field label {
  font-size: 14px;
  font-weight: 500;
  line-height: 1.5;
}

.field select,
.field textarea {
  box-sizing: border-box;
  width: 100%;
  padding: 11px 14px;
  border: 1px solid #cbd5d1;
  border-radius: 7px;
  background: #fff;
  color: #17231f;
  font: inherit;
}

.field select {
  min-height: 46px;
}

.field textarea {
  min-height: 110px;
  resize: vertical;
}

.field select:focus,
.field textarea:focus {
  outline: 2px solid #78b49d;
  outline-offset: 2px;
}
