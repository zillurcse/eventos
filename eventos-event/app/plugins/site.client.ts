/**
 * Resolve the event microsite from the subdomain at app boot, before the first
 * page renders its branded shell. SPA-only (ssr:false), so this always runs in
 * the browser where window.location is available.
 */
export default defineNuxtPlugin(async () => {
  const site = useSiteStore()
  await site.fetchSite()
})
