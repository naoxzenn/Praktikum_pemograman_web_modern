
export default defineNuxtConfig({
  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      apiBase: 'http://127.0.0.1:8000/api'
    }
  },

  routeRules: {
    '/admin': { ssr: false }
  }
})
