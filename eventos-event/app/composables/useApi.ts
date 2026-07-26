import { useAuthStore } from '~/stores/auth'

/**
 * Typed wrapper around $fetch bound to the EventOS API base URL. Attaches the
 * Sanctum bearer token and signs the user out on 401.
 */
export function useApi() {
  const { public: { apiBase } } = useRuntimeConfig()
  const auth = useAuthStore()

  return $fetch.create({
    baseURL: apiBase as string,
    onRequest({ options }) {
      const headers = new Headers(options.headers as HeadersInit)

      // Scope every call to the event this host/subdomain resolves to.
      for (const [k, v] of Object.entries(eventIdentityHeaders())) {
        headers.set(k, v)
      }

      if (auth.token) headers.set('Authorization', `Bearer ${auth.token}`)

      options.headers = headers
    },
    onResponseError({ response }) {
      if (response.status === 401) auth.logout()
    },
  })
}
