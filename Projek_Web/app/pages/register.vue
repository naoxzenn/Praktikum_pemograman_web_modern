<script setup lang="ts">
const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const { setAuth } = useAuth()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const sedangDaftar = ref(false)
const kesalahan = ref('')
const errorFields = ref<Record<string, string[]>>({})

async function daftar() {
  kesalahan.value = ''
  errorFields.value = {}
  sedangDaftar.value = true

  try {
    const response = await $fetch<{
      message: string
      token: string
      user: {
        id: number
        name: string
        email: string
        role: string
      }
    }>(`${apiUrl}/register`, {
      method: 'POST',
      body: {
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
        password_confirmation: form.password_confirmation,
      },
      headers: {
        Accept: 'application/json',
      },
    })

    setAuth(response.token, response.user)
    await navigateTo('/')
  } catch (error: any) {
    if (error.status === 422 || error.statusCode === 422) {
      errorFields.value = error.data?.errors || {}
      kesalahan.value = error.data?.message || 'Periksa kembali isian formulir.'
    } else {
      kesalahan.value =
        error.data?.message || 'Registrasi gagal. Coba lagi nanti.'
    }
  } finally {
    sedangDaftar.value = false
  }
}
</script>

<template>
  <main class="section">
    <div class="container" style="max-width: 480px">
      <section class="card">
        <span class="eyebrow">Portal Layanan Mahasiswa</span>
        <h1>Buat akun baru</h1>
        <p class="muted">
          Daftarkan diri sebagai mahasiswa untuk menggunakan layanan portal.
        </p>

        <form @submit.prevent="daftar">
          <AppField
            v-model="form.name"
            label="Nama lengkap"
            name="reg-name"
            placeholder="Masukkan nama lengkap"
            autocomplete="name"
            required
            :error="errorFields.name?.[0]"
          />

          <AppField
            v-model="form.email"
            label="Email"
            name="reg-email"
            type="email"
            placeholder="nama@kampus.ac.id"
            autocomplete="email"
            required
            :error="errorFields.email?.[0]"
          />

          <AppField
            v-model="form.password"
            label="Password"
            name="reg-password"
            type="password"
            placeholder="Minimal 8 karakter"
            autocomplete="new-password"
            required
            :error="errorFields.password?.[0]"
          />

          <AppField
            v-model="form.password_confirmation"
            label="Konfirmasi password"
            name="reg-password-confirm"
            type="password"
            placeholder="Ulangi password"
            autocomplete="new-password"
            required
          />

          <AppAlert
            v-if="kesalahan"
            type="error"
            :message="kesalahan"
          />

          <button
            class="button"
            type="submit"
            :disabled="sedangDaftar"
            style="width: 100%; margin-top: 20px"
          >
            {{ sedangDaftar ? 'Mendaftarkan...' : 'Daftar' }}
          </button>
        </form>

        <p class="muted" style="margin-top: 20px; text-align: center">
          Sudah punya akun?
          <NuxtLink to="/login" style="color: var(--green); font-weight: 600">
            Masuk di sini
          </NuxtLink>
        </p>
      </section>
    </div>
  </main>
</template>
