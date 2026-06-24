// Organizer area: requires an active organization membership.
export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()
  auth.init()

  if (!auth.isAuthed) return navigateTo('/login')
  if (!auth.user) await auth.fetchMe()
  if (!auth.isAuthed) return navigateTo('/login')
  if (!auth.isOrganizer) return navigateTo(auth.home)
})
