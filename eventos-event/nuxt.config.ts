// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },

  modules: ['@pinia/nuxt'],

  css: ['~/assets/main.css'],

  // Pre-bundle the WS client deps so first load doesn't trigger a Vite reload.
  vite: { optimizeDeps: { include: ['laravel-echo', 'pusher-js'] } },

  // EventOS Event app is a pure SPA (organizer + attendee experience): no SSR,
  // so all API calls run in the browser and "localhost:8088" resolves to the
  // host — not the Nuxt container.
  ssr: false,

  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8088/api/v1',
      reverb: {
        key: 'eventos-key',
        host: 'localhost',
        port: 8081,
        scheme: 'http',
      },
    },
  },

  // Windows + Docker bind mounts don't propagate inotify events, so poll.
  vite: {
    server: {
      watch: { usePolling: true, interval: 300 },
    },
  },
})
