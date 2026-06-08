export default defineNuxtConfig({
  srcDir: 'app',
  modules: ['@pinia/nuxt', '@nuxt/eslint'],
  css: ['driver.js/dist/driver.css', '~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api',
    },
  },
  vite: {
    server: {
      allowedHosts: [
        'e190-2600-4041-5ce7-c000-dccf-17aa-a56a-2872.ngrok-free.app',
      ],
    },
  },
  typescript: {
    strict: true,
  },
})
