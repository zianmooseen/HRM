export default defineNuxtConfig({
  srcDir: 'app',
  modules: ['@pinia/nuxt'],
  css: ['driver.js/dist/driver.css', '~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api',
    },
  },
  typescript: {
    strict: true,
  },
})
