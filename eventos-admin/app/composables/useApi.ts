import { useAuthStore } from '~/stores/auth'

/**
 * Typed wrapper around $fetch bound to the EventOS API base URL. Attaches the
 * platform-staff bearer token and signs out on 401.
 */
export function useApi() {
  const { public: { apiBase } } = useRuntimeConfig()
  const auth = useAuthStore()

  return $fetch.create({
    baseURL: apiBase as string,
    onRequest({ options }) {
      if (auth.token) {
        const headers = new Headers(options.headers as HeadersInit)
        headers.set('Authorization', `Bearer ${auth.token}`)
        options.headers = headers
      }
    },
    onResponseError({ response }) {
      if (response.status === 401) auth.logout()
    },
  })
}
