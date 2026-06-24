// Generic "must be signed in" guard; loads the user if needed.
export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()
  auth.init()

  if (!auth.isAuthed) return navigateTo('/login')
  if (!auth.user) await auth.fetchMe()
  if (!auth.isAuthed) return navigateTo('/login')
})
