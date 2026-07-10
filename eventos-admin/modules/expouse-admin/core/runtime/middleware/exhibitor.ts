// Exhibitor area: requires an exhibitor-team membership (admin or staff).
export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()
  auth.init()

  if (!auth.isAuthed) return navigateTo('/login')
  if (!auth.user) await auth.fetchMe()
  if (!auth.isAuthed) return navigateTo('/login')
  if (!auth.isExhibitor) return navigateTo(auth.home)

  // Enforce Showcase entitlements: a page may declare a required feature key
  // via `definePageMeta({ feature: '…' })`; bounce to the booth home if the
  // active exhibitor isn't entitled.
  const feature = to.meta.feature as string | undefined
  if (feature && !auth.hasFeature(feature)) return navigateTo('/exhibitor')
})
