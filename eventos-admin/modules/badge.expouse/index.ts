import { defineNuxtModule, createResolver, addComponent, addComponentsDir, addLayout, addPlugin, extendPages } from '@nuxt/kit'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'

/**
 * badge.expouse — the badge-design canvas editor, integrated into the admin as a
 * feature. It was a standalone Nuxt 4 app (an internal `designbadge` layer,
 * Tailwind v3); here it runs inside the admin's single Nuxt v4 build, mounted in
 * the per-event console. Specifics handled (mirrors the floor.expouse module):
 *
 *  - `@badge` alias points at this module's app/ dir. The sources used the `@/`
 *    alias (→ the layer root) for all internal imports; that was rewritten to
 *    `@badge/` since `@`/`~` would otherwise resolve to the ADMIN srcDir.
 *  - Components are auto-imported (badge templates reference them by name);
 *    composables/stores are NOT — they're imported explicitly via @badge.
 *  - The layout is registered under the `badge` name (namespaced so it can't
 *    clash with the admin's own `default` layout) and applied via route meta so
 *    the editor renders full-screen instead of inside the persona sidebar shell.
 *  - Pages are remounted under the event console (`/org/events/:id/badge`); the
 *    :id route param is the event uuid the badge design is scoped to.
 *  - Badge uses @nuxt/icon's <NuxtIcon> (its <Icon> was rewritten) because the
 *    admin has its own hand-rolled <Icon> component that only knows a fixed set
 *    of SVG paths and can't render iconify `name="mdi:..."` icons.
 *  - The pdf plugin (provides $jsPDF / $html2canvas, used by the preview/export)
 *    is registered as a client plugin.
 */
export default defineNuxtModule({
  meta: { name: 'badge-expouse' },
  setup(_, nuxt) {
    const { resolve } = createResolver(import.meta.url)

    // badge's internal import alias (was `@/`)
    nuxt.options.alias['@badge'] = resolve('app')

    addComponentsDir({ path: resolve('app/components') })

    // The badge/floor templates use <NuxtIcon name="mdi:…"> to render iconify
    // icons. @nuxt/icon only registers its component as <Icon> (which the admin's
    // own hand-rolled <Icon> overrides), and its `componentName` config option
    // isn't honored in this version — so <NuxtIcon> didn't exist and every
    // iconify icon rendered blank. Register @nuxt/icon's real component under the
    // <NuxtIcon> name explicitly, resolved to an absolute path (the package's
    // `./*`→`./dist/*` export has no extension, so the bare specifier won't load).
    const req = createRequire(import.meta.url)
    const iconComponent = join(dirname(req.resolve('@nuxt/icon')), 'runtime/components/index.js')
    addComponent({ name: 'NuxtIcon', filePath: iconComponent, export: 'default' })

    addLayout(resolve('app/layouts/default.vue'), 'badge')

    addPlugin({ src: resolve('app/plugins/pdf.client.ts'), mode: 'client' })

    extendPages((pages) => {
      pages.push(
        // The badge editor, scoped to an event via the :id route param (the event
        // uuid). A specific design is selected with `?design=<id>` (omit to start
        // a fresh design). Guarded by `auth` — badge uses the signed-in user's
        // bearer token via the admin's useApi(). page-builder.vue is the complete
        // store-based editor (front/back canvas + sidebar + Save → badge_designs);
        // create.vue is an older local-only variant and is intentionally unrouted.
        {
          name: 'org-events-id-badge',
          path: '/org/events/:id()/badge',
          file: resolve('app/pages/my-badge/page-builder.vue'),
          meta: { layout: 'badge', middleware: 'auth' },
        },
        {
          name: 'org-events-id-badge-preview',
          path: '/org/events/:id()/badge/preview',
          file: resolve('app/pages/my-badge/preview-badge.vue'),
          meta: { layout: 'badge', middleware: 'auth' },
        },
      )
    })
  },
})
