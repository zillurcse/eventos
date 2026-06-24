// Control-plane pages: platform staff only. Non-platform users go to their own home.
export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()
  auth.init()

  if (!auth.isAuthed) return navigateTo('/login')
  if (!auth.user) await auth.fetchMe()
  if (!auth.isAuthed) return navigateTo('/login')
  if (!auth.isPlatform) return navigateTo(auth.home)
})
