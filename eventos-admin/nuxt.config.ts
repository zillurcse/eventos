// https://nuxt.com/docs/api/configuration/nuxt-config
import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: false },

  // The whole admin app is the `expouse-admin` project: its feature "micro
  // projects" live under modules/expouse-admin/<feature>/, each a self-contained
  // local Nuxt module registering its own pages, components, composables,
  // stores, middleware and layouts via @nuxt/kit. Nuxt auto-discovers
  // modules/expouse-admin/index.ts, which installs the nested features.
  modules: [
    '@pinia/nuxt',
    'vue-sonner/nuxt',
    // Modules required by the floor.expouse feature (modules/floor.expouse).
    '@nuxt/icon',
    '@nuxt/image',
    '@nuxtjs/google-fonts',
    'nuxt-color-picker',
    'nuxt-toast',
    'pinia-plugin-persistedstate/nuxt',
  ],

  // Font families the floor-plan editor offers for canvas text. download:false
  // keeps them as CDN <link>s (no build-time fetch) — fine for this SPA.
  googleFonts: {
    download: false,
    families: {
      Cairo: true, Roboto: true, 'Open+Sans': true, Lato: true, Montserrat: true,
      Oswald: true, Raleway: true, Poppins: true, 'Noto+Sans': true, Ubuntu: true,
      Merriweather: true, 'PT+Sans': true, 'Roboto+Condensed': true,
      'Playfair+Display': true, Nunito: true, Mukta: true, Inconsolata: true,
      Quicksand: true, 'Fira+Sans': true, Assistant: true,
    },
    display: 'swap',
  },

  build: {
    transpile: ['pinia-plugin-persistedstate'],
  },

  // Global design system (Tailwind v4 + the shared component layer). It lives in
  // the core feature but is registered here at the root: nuxt.options.css is
  // snapshotted before nested installModule() setups run, so a module-level
  // push from core wouldn't be picked up.
  css: ['~~/modules/expouse-admin/core/runtime/assets/main.css'],

  // Pages are contributed programmatically by the feature modules (via
  // extendPages), so enable the router even though app/ has no pages/ dir.
  pages: true,

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
