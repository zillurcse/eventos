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

      // Scope every call to the event this subdomain resolves to ("API call
      // under the subdomain") — the API reads this for public/event context.
      const sub = useEventSubdomain()
      if (sub) headers.set('X-Event-Subdomain', sub)

      if (auth.token) headers.set('Authorization', `Bearer ${auth.token}`)

      options.headers = headers
    },
    onResponseError({ response }) {
      if (response.status === 401) auth.logout()
    },
  })
}
