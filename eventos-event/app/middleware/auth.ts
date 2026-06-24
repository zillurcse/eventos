export default defineNuxtRouteMiddleware(() => {
  const auth = useAuthStore()
  auth.init()

  if (!auth.isAuthed) {
    return navigateTo('/login')
  }
})
