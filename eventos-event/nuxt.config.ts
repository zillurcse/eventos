// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },

  modules: ['@pinia/nuxt'],

  css: ['~/assets/main.css'],

  // EventOS Event app is a pure SPA (per-event public microsite): no SSR, so all
  // API calls run in the browser and "localhost:8088" resolves to the host —
  // not the Nuxt container.
  ssr: false,

  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8088/api/v1',
      // Platform apex for subdomain → event resolution (see useEventSubdomain).
      // Each event is served at <subdomain>.<eventBaseDomain>, e.g. edu.expouse.test.
      eventBaseDomain: 'expouse.test',
      reverb: {
        key: 'eventos-key',
        host: 'localhost',
        port: 8081,
        scheme: 'http',
      },
    },
  },

  vite: {
    // Pre-bundle client deps so first load doesn't trigger a Vite reload/re-optimize
    // (livekit-client powers the breakout Rooms video, laravel-echo/pusher the WS).
    optimizeDeps: { include: ['laravel-echo', 'pusher-js', 'livekit-client', 'uqr'] },
    server: {
      // Windows + Docker bind mounts don't propagate inotify events, so poll.
      watch: { usePolling: true, interval: 300 },
      // Accept per-event subdomain Host headers (e.g. edu.expouse.test) in dev.
      allowedHosts: ['.expouse.test', 'localhost', '127.0.0.1'],
    },
  },
})
