// https://nuxt.com/docs/api/configuration/nuxt-config
import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },

  modules: ['@pinia/nuxt'],

  css: ['~/assets/main.css'],

  // EventOS Admin is a pure SPA (Super-Admin control plane): no SSR, so all
  // API calls run in the browser and "localhost:8088" resolves to the host.
  ssr: false,

  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8088/api/v1',
    },
  },

  // Windows + Docker bind mounts don't propagate inotify events, so poll.
  vite: {
    plugins: [tailwindcss()],
    server: {
      watch: { usePolling: true, interval: 300 },
    },
  },
})
