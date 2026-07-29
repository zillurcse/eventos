/**
 * Resolve the event microsite from the subdomain at app boot, before the first
 * page renders its branded shell. SPA-only (ssr:false), so this always runs in
 * the browser where window.location is available.
 *
 * Also re-fetch when the tab becomes visible again so admin toggles (e.g.
 * Navigation › Modules › Chat) show up without a hard reload.
 */
export default defineNuxtPlugin(async () => {
  const site = useSiteStore()
  await site.fetchSite()

  if (!import.meta.client) return

  let lastFetch = Date.now()
  const refresh = () => {
    // Avoid hammering /public/site on rapid focus flips.
    if (Date.now() - lastFetch < 5_000) return
    lastFetch = Date.now()
    void site.fetchSite().then(() => {
      const auth = useAuthStore()
      if (auth.isAuthed && site.chatModuleEnabled) {
        useChatStore().fetchCapabilities({ force: true })
      }
    })
  }

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') refresh()
  })
  window.addEventListener('focus', refresh)
})
