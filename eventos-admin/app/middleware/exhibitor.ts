// Exhibitor area: requires an exhibitor-team membership (admin or staff).
export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()
  auth.init()

  if (!auth.isAuthed) return navigateTo('/login')
  if (!auth.user) await auth.fetchMe()
  if (!auth.isAuthed) return navigateTo('/login')
  if (!auth.isExhibitor) return navigateTo(auth.home)
})
