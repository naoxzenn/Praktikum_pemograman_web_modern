export default defineNuxtRouteMiddleware((to) => {
    // Pemeriksaan sesi browser tidak dapat dilakukan saat SSR.
    if (import.meta.server) {
        return
    }

    const token = sessionStorage.getItem('portal_token')
    const userJson = sessionStorage.getItem('portal_user')

    if (!token || !userJson) {
        if (to.path !== '/login') {
            return navigateTo('/login', { replace: true })
        }
        return
    }

    let user: { id?: number; role?: string }

    try {
        user = JSON.parse(userJson)
    } catch {
        sessionStorage.removeItem('portal_token')
        sessionStorage.removeItem('portal_user')
        return navigateTo('/login', { replace: true })
    }

    if (!user.id || !['mahasiswa', 'petugas'].includes(user.role || '')) {
        sessionStorage.removeItem('portal_token')
        sessionStorage.removeItem('portal_user')
        return navigateTo('/login', { replace: true })
    }

    if (to.path.startsWith('/admin') && user.role !== 'petugas') {
        return navigateTo('/', { replace: true })
    }

    if (to.path.startsWith('/blog/artikel-saya') && user.role !== 'mahasiswa') {
        return navigateTo('/blog', { replace: true })
    }

    if (to.path === '/' && user.role === 'petugas') {
        return navigateTo('/admin', { replace: true })
    }
})
