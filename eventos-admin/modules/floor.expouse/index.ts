import { defineNuxtModule, createResolver, addComponentsDir, addLayout, extendPages } from '@nuxt/kit'

/**
 * floor.expouse — the floor-plan canvas editor, integrated into the admin as a
 * feature. It was a standalone Nuxt app (Tailwind v3); here it runs inside the
 * admin's single Nuxt v4 build. Specifics handled:
 *
 *  - `@floorplan` alias points at floor's app/ dir (its sources use it for all
 *    internal imports; the original `~/` was rewritten to `@floorplan/` since
 *    `~` would otherwise resolve to the ADMIN srcDir).
 *  - Components are auto-imported (floor templates reference them by name);
 *    composables/stores are NOT — floor imports those explicitly via @floorplan,
 *    which also sidesteps duplicate names like useCanvasRendering.
 *  - Layouts are registered under floor-namespaced names to avoid clashing with
 *    the admin's `default` layout.
 *  - Pages are remounted under /floor (index → /floor, preview → /floor/preview)
 *    so they don't collide with the admin's `/` route.
 *  - Floor uses @nuxt/icon's <NuxtIcon> (its <Icon> was rewritten) so the admin's
 *    own <Icon> component is untouched.
 */
export default defineNuxtModule({
  meta: { name: 'floor-expouse' },
  setup(_, nuxt) {
    const { resolve } = createResolver(import.meta.url)

    // floor's internal import alias
    nuxt.options.alias['@floorplan'] = resolve('app')

    addComponentsDir({ path: resolve('app/components') })

    addLayout(resolve('app/layouts/default.vue'), 'floorplan')
    addLayout(resolve('app/layouts/blank.vue'), 'floorplan-blank')

    extendPages((pages) => {
      pages.push(
        // Mounted under the event console: the floor editor is scoped to an event
        // via the :id route param (the event uuid). Guarded by `auth` — floor uses
        // the signed-in user's bearer token (no base64 event/user tokens).
        {
          name: 'org-events-id-floor',
          path: '/org/events/:id()/floor',
          file: resolve('app/pages/index.vue'),
          meta: { middleware: 'auth' },
        },
        {
          name: 'org-events-id-floor-preview',
          path: '/org/events/:id()/floor/preview',
          file: resolve('app/pages/preview.vue'),
          meta: { middleware: 'auth' },
        },
      )
    })
  },
})
