import { defineNuxtModule, installModule } from '@nuxt/kit'
import core from './core'
import platform from './platform'
import organizer from './organizer'
import event from './event'
import mail from './mail'
import exhibitor from './exhibitor'

/**
 * expouse-admin — the EXPOUSE admin project. A single parent module that groups
 * every feature "micro project" under `modules/expouse-admin/<feature>/` and
 * installs them. Nuxt auto-discovers this file (modules/<dir>/index.ts); the
 * nested feature modules are NOT auto-scanned (Nuxt only looks one level deep),
 * so they are installed explicitly here. `core` goes first so its shared layout,
 * stylesheet and UI kit are registered before the domain features.
 */
export default defineNuxtModule({
  meta: { name: 'expouse-admin' },
  async setup() {
    for (const feature of [core, platform, organizer, event, mail, exhibitor]) {
      await installModule(feature)
    }
  },
})
