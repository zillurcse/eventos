// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },

  modules: ['@pinia/nuxt', 'vue-sonner/nuxt'],

  css: ['~/assets/main.css'],

  app: {
    head: {
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap' },
      ],
    },
  },

  // EventOS Event app is a pure SPA (per-event public microsite): no SSR, so all
  // API calls run in the browser and "localhost:8088" resolves to the host —
  // not the Nuxt container.
  ssr: false,

  runtimeConfig: {
    // Server-only: where the image proxy (server/api/image-proxy) reaches
    // MinIO from inside its own container. Empty in prod, where the stored
    // URL is already a real public bucket/CDN address the proxy can hit directly.
    minioInternalBase: '',
    public: {
      apiBase: 'http://localhost:8088/api/v1',
      // Platform apex for subdomain → event resolution (see useEventSubdomain).
      // Each event is served at <subdomain>.<eventBaseDomain>, e.g. edu.expouse.test.
      eventBaseDomain: 'expouse.test',
      // Jitsi server for embedded in-page video sessions (host = "jitsi").
      // Public server by default; point at a self-hosted instance for
      // privacy/scale via NUXT_PUBLIC_JITSI_DOMAIN.
      jitsiDomain: 'meet.jit.si',
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
    optimizeDeps: { include: ['laravel-echo', 'pusher-js', 'livekit-client', 'uqr', 'swiper/vue', 'swiper/modules'] },
    server: {
      // Windows + Docker bind mounts don't propagate inotify events, so poll.
      watch: { usePolling: true, interval: 300 },
      // Accept per-event subdomain Host headers (e.g. edu.expouse.test) in dev.
      allowedHosts: ['.expouse.test', 'localhost', '127.0.0.1'],
    },
  },
})
