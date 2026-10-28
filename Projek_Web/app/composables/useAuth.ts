interface AuthUser {
  id: number
  name: string
  email: string
  role: string
}

export const useAuth = () => {
  const user = useState<AuthUser | null>('auth_user', () => null)
  const token = useState<string | null>('auth_token', () => null)

  const isLoggedIn = computed(() => !!user.value && !!token.value)
  const isMahasiswa = computed(() => user.value?.role === 'mahasiswa')
  const isPetugas = computed(() => user.value?.role === 'petugas')

  /** Memuat status autentikasi dari sessionStorage ke state reaktif. Hanya berjalan di client. */
  function init() {
    if (import.meta.server) return
    const savedToken = sessionStorage.getItem('portal_token')
    const savedUser = sessionStorage.getItem('portal_user')
    if (savedToken && savedUser) {
      try {
        token.value = savedToken
        user.value = JSON.parse(savedUser)
      } catch {
        clear()
      }
    }
  }

  /** Menyimpan token dan data pengguna ke state dan sessionStorage. */
  function setAuth(newToken: string, newUser: AuthUser) {
    token.value = newToken
    user.value = newUser
    if (import.meta.client) {
      sessionStorage.setItem('portal_token', newToken)
      sessionStorage.setItem('portal_user', JSON.stringify(newUser))
    }
  }

  /** Menghapus data autentikasi dari state dan sessionStorage. */
  function clear() {
    token.value = null
    user.value = null
    if (import.meta.client) {
      sessionStorage.removeItem('portal_token')
      sessionStorage.removeItem('portal_user')
    }
  }

  /** Mengembalikan header HTTP untuk request terautentikasi. Membaca langsung dari sessionStorage untuk menghindari masalah timing. */
  function authHeaders(): Record<string, string> {
    const headers: Record<string, string> = { Accept: 'application/json' }
    const currentToken = import.meta.client
      ? (sessionStorage.getItem('portal_token') ?? token.value)
      : token.value
    if (currentToken) {
      headers.Authorization = `Bearer ${currentToken}`
    }
    return headers
  }

  return { user, token, isLoggedIn, isMahasiswa, isPetugas, init, setAuth, clear, authHeaders }
}
