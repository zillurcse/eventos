export default defineNuxtRouteMiddleware(() => {
  const auth = useAuthStore()
  auth.init()

  if (!auth.isAuthed) {
    // The branded landing page ("/") is the sign-in surface for the microsite.
    return navigateTo('/')
  }
})
