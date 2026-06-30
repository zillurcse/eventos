import { existsSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import {
  defineNuxtModule,
  createResolver,
  addComponentsDir,
  addImportsDir,
  addRouteMiddleware,
  addLayout,
  extendPages,
} from '@nuxt/kit'

/**
 * Shared scaffold for an expouse-admin feature module — a self-contained
 * "micro project" under `modules/expouse-admin/<feature>/`. Each feature keeps
 * its own `runtime/` tree and this helper wires whichever parts exist into Nuxt:
 *
 *   runtime/components   -> auto-imported components (Nuxt dir-prefix naming)
 *   runtime/composables  -> auto-imported composables
 *   runtime/utils        -> auto-imported utils
 *   runtime/stores       -> auto-imported Pinia stores
 *   runtime/middleware   -> named route middleware (file name = middleware name)
 *   runtime/layouts      -> named layouts (file name = layout name)
 *   runtime/pages        -> file-based routes, registered via extendPages
 *
 * The global stylesheet (core/runtime/assets/main.css) is registered in the
 * root nuxt.config instead, because nuxt.options.css is snapshotted before the
 * nested installModule() setups run.
 *
 * Because pages are added through extendPages (the `modules/` directory does
 * not auto-scan pages the way layers do), we reproduce Nuxt's flat file→route
 * conventions here: `index` is dropped, `[param]` becomes `:param()`, and the
 * route name is the path segments joined by `-`.
 */
export interface FeatureModuleOptions {
  /** Unique module name, e.g. "feature-event". */
  name: string
}

function listFiles(dir: string, ext: string): string[] {
  if (!existsSync(dir)) return []
  const out: string[] = []
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry)
    if (statSync(full).isDirectory()) out.push(...listFiles(full, ext))
    else if (entry.endsWith(ext)) out.push(full)
  }
  return out
}

function fileToRoute(pagesDir: string, file: string) {
  const rel = relative(pagesDir, file).replace(/\\/g, '/').replace(/\.vue$/, '')
  let segments = rel.split('/')
  if (segments[segments.length - 1] === 'index') segments = segments.slice(0, -1)

  const toName = (s: string) => s.replace(/^\[(\.{3})?(.+?)\]$/, '$2')
  const toPath = (s: string) =>
    s.replace(/^\[\.{3}(.+?)\]$/, ':$1(.*)*').replace(/^\[(.+?)\]$/, ':$1()')

  const name = segments.length ? segments.map(toName).join('-') : 'index'
  const path = segments.length ? '/' + segments.map(toPath).join('/') : '/'
  return { name, path, file }
}

export function defineFeatureModule(url: string, options: FeatureModuleOptions) {
  return defineNuxtModule({
    meta: { name: options.name },
    setup() {
      const { resolve } = createResolver(url)
      const has = (p: string) => existsSync(resolve(p))

      if (has('runtime/components')) {
        addComponentsDir({ path: resolve('runtime/components') })
      }
      if (has('runtime/composables')) addImportsDir(resolve('runtime/composables'))
      if (has('runtime/utils')) addImportsDir(resolve('runtime/utils'))
      if (has('runtime/stores')) addImportsDir(resolve('runtime/stores'))

      for (const mw of listFiles(resolve('runtime/middleware'), '.ts')) {
        const name = mw.replace(/\\/g, '/').split('/').pop()!.replace(/\.ts$/, '')
        addRouteMiddleware({ name, path: mw })
      }

      for (const layout of listFiles(resolve('runtime/layouts'), '.vue')) {
        const name = layout.replace(/\\/g, '/').split('/').pop()!.replace(/\.vue$/, '')
        addLayout(layout, name)
      }

      const pagesDir = resolve('runtime/pages')
      if (existsSync(pagesDir)) {
        const routes = listFiles(pagesDir, '.vue').map((f) => fileToRoute(pagesDir, f))
        extendPages((pages) => {
          for (const r of routes) pages.push(r)
        })
      }
    },
  })
}
