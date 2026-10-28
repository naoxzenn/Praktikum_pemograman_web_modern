<script setup lang="ts">
const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const { setAuth } = useAuth()

const email = ref('')
const password = ref('')
const sedangLogin = ref(false)
const kesalahan = ref('')

async function login() {
  kesalahan.value = ''
  sedangLogin.value = true

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
    }>(`${apiUrl}/login`, {
      method: 'POST',
      body: {
        email: email.value,
        password: password.value,
      },
      headers: {
        Accept: 'application/json',
      },
    })

    setAuth(response.token, response.user)

    await navigateTo(
      response.user.role === 'petugas' ? '/admin' : '/'
    )
  } catch (error: any) {
    kesalahan.value =
      error.data?.message || 'Login gagal. Periksa email dan password.'
  } finally {
    sedangLogin.value = false
  }
}
</script>

<template>
  <main class="section">
    <div class="container" style="max-width: 480px">
      <section class="card">
        <span class="eyebrow">Portal Layanan Mahasiswa</span>
        <h1>Masuk ke akun</h1>
        <p class="muted">
          Masukkan email dan password akun yang sudah terdaftar.
        </p>

        <form @submit.prevent="login">
          <div class="field">
            <label for="login-email">Email</label>
            <input
              id="login-email"
              v-model.trim="email"
              type="email"
              autocomplete="username"
              required
              placeholder="nama@kampus.ac.id"
            />
          </div>

          <div class="field">
            <label for="login-password">Password</label>
            <input
              id="login-password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
              placeholder="Masukkan password"
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
            :disabled="sedangLogin"
            style="width: 100%; margin-top: 20px"
          >
            {{ sedangLogin ? 'Memproses...' : 'Masuk' }}
          </button>
        </form>

        <p class="muted" style="margin-top: 20px; text-align: center">
          Belum punya akun?
          <NuxtLink to="/register" style="color: var(--green); font-weight: 600">
            Daftar di sini
          </NuxtLink>
        </p>
      </section>
    </div>
  </main>
</template>