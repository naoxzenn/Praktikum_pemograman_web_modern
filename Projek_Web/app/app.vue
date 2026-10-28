<script setup lang="ts">
const config = useRuntimeConfig()
const apiUrl = config.public.apiBase
const { user, isLoggedIn, isMahasiswa, isPetugas, init, clear, authHeaders } = useAuth()

const sedangLogout = ref(false)

interface NotificationItem {
  id: number
  type: string
  title: string
  message: string
  read_at: string | null
  created_at: string
}

const notifikasiList = ref<NotificationItem[]>([])
const jumlahBelumDibaca = ref(0)
const isNotifOpen = ref(false)

async function muatNotifikasi() {
  if (!isLoggedIn.value) return
  try {
    const res = await $fetch<{ unread_count: number; notifications: NotificationItem[] }>(
      `${apiUrl}/notifications`,
      { headers: authHeaders() }
    )
    jumlahBelumDibaca.value = res.unread_count
    notifikasiList.value = res.notifications
  } catch {
    // Abaikan
  }
}

async function tandaiSemuaDibaca() {
  try {
    await $fetch(`${apiUrl}/notifications/mark-all-read`, {
      method: 'POST',
      headers: authHeaders(),
    })
    jumlahBelumDibaca.value = 0
    notifikasiList.value.forEach((n) => (n.read_at = new Date().toISOString()))
  } catch {
    // Abaikan
  }
}

function toggleNotif() {
  isNotifOpen.value = !isNotifOpen.value
  if (isNotifOpen.value) {
    muatNotifikasi()
  }
}

onMounted(() => {
  init()
  if (isLoggedIn.value) {
    muatNotifikasi()
  }
})

watch(isLoggedIn, (login) => {
  if (login) {
    muatNotifikasi()
  } else {
    notifikasiList.value = []
    jumlahBelumDibaca.value = 0
    isNotifOpen.value = false
  }
})

async function logout() {
  sedangLogout.value = true
  try {
    await $fetch(`${apiUrl}/logout`, {
      method: 'POST',
      headers: authHeaders(),
    })
  } catch {
    // Tetap logout lokal meskipun server gagal
  } finally {
    clear()
    sedangLogout.value = false
    await navigateTo('/login')
  }
}
</script>

<template>
  <div>
    <header class="site-header">
      <nav class="container navbar" aria-label="Navigasi utama">
        <NuxtLink to="/" class="brand">Portal Mahasiswa</NuxtLink>

        <div class="nav-links">
          <NuxtLink to="/">Beranda</NuxtLink>
          <NuxtLink to="/layanan">Daftar Layanan</NuxtLink>
          <NuxtLink to="/blog">Blog</NuxtLink>

          <template v-if="isMahasiswa">
            <NuxtLink to="/pengajuan">Pengajuan</NuxtLink>
            <NuxtLink to="/status">Lacak Status</NuxtLink>
            <NuxtLink to="/blog/artikel-saya">Artikel Saya</NuxtLink>
          </template>

          <NuxtLink v-if="isPetugas" to="/admin">
            Dashboard Petugas
          </NuxtLink>

          <template v-if="isLoggedIn">
            <!-- Notification Bell -->
            <div class="notif-wrapper">
              <button
                type="button"
                class="btn-notif"
                title="Notifikasi"
                @click="toggleNotif"
              >
                🔔
                <span v-if="jumlahBelumDibaca > 0" class="notif-badge">
                  {{ jumlahBelumDibaca > 9 ? '9+' : jumlahBelumDibaca }}
                </span>
              </button>

              <!-- Dropdown Notifikasi -->
              <div v-if="isNotifOpen" class="notif-dropdown">
                <div class="notif-header">
                  <strong>Pemberitahuan</strong>
                  <button
                    v-if="jumlahBelumDibaca > 0"
                    type="button"
                    class="btn-mark-all"
                    @click="tandaiSemuaDibaca"
                  >
                    Tandai dibaca
                  </button>
                </div>

                <div v-if="notifikasiList.length === 0" class="notif-empty">
                  Tidak ada pemberitahuan baru.
                </div>

                <div v-else class="notif-list">
                  <div
                    v-for="item in notifikasiList"
                    :key="item.id"
                    class="notif-item"
                    :class="{ unread: !item.read_at }"
                  >
                    <div class="notif-item-title">{{ item.title }}</div>
                    <div class="notif-item-msg">{{ item.message }}</div>
                    <div class="notif-item-time">
                      {{ new Date(item.created_at).toLocaleString('id-ID') }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <span class="nav-user">{{ user?.name }}</span>
            <button
              class="button button-secondary btn-logout"
              type="button"
              :disabled="sedangLogout"
              @click="logout"
            >
              {{ sedangLogout ? 'Keluar...' : 'Keluar' }}
            </button>
          </template>

          <NuxtLink v-else to="/login" class="button">Masuk</NuxtLink>
        </div>
      </nav>
    </header>

    <NuxtPage />

    <footer class="site-footer">
      <div class="container">
        <strong>Portal Layanan Mahasiswa</strong>
        <p>Informasi dan layanan administrasi kampus, dalam satu tempat.</p>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.notif-wrapper {
  position: relative;
  display: inline-block;
}
.btn-notif {
  background: none;
  border: 1px solid var(--line, #e5e7eb);
  border-radius: 50%;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  position: relative;
  font-size: 16px;
}
.notif-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #dc2626;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  border-radius: 999px;
  padding: 2px 5px;
  line-height: 1;
}
.notif-dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  width: 320px;
  background: #fff;
  border: 1px solid var(--line, #e5e7eb);
  border-radius: 8px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
  z-index: 100;
  overflow: hidden;
}
.notif-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 14px;
  background: #f8fafc;
  border-bottom: 1px solid var(--line, #e5e7eb);
  font-size: 13px;
}
.btn-mark-all {
  background: none;
  border: none;
  color: var(--green, #10b981);
  font-size: 12px;
  cursor: pointer;
  text-decoration: underline;
}
.notif-list {
  max-height: 340px;
  overflow-y: auto;
}
.notif-item {
  padding: 10px 14px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 13px;
}
.notif-item.unread {
  background: #f0fdf4;
}
.notif-item-title {
  font-weight: 600;
  color: #1e293b;
}
.notif-item-msg {
  color: #475569;
  margin: 3px 0;
  font-size: 12px;
  line-height: 1.4;
}
.notif-item-time {
  font-size: 11px;
  color: #94a3b8;
}
.notif-empty {
  padding: 20px;
  text-align: center;
  color: #64748b;
  font-size: 13px;
}
.nav-user {
  font-size: 13px;
  font-weight: 600;
  color: var(--green);
}
.btn-logout {
  padding: 7px 14px;
  min-height: auto;
  font-size: 13px;
}
</style>
