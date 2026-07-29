import { toast } from 'vue-sonner'
import { useAuthStore } from '~/stores/auth'

declare module 'ofetch' {
  interface FetchOptions {
    /**
     * Skip the global 403 toast. Use for intentional probes (e.g. organizer-only
     * endpoints that attendees are expected to miss).
     */
    silent?: boolean
  }
}

/**
 * Typed wrapper around $fetch bound to the EventOS API base URL. Attaches the
 * Sanctum bearer token, signs the user out on 401, and toasts 403 messages.
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
    onResponseError({ response, options }) {
      if (response.status === 401) {
        auth.logout()
        return
      }

      if (response.status !== 403 || !import.meta.client) return
      if (options.silent) return

      const body = response._data as { message?: string } | string | null | undefined
      const fromBody = typeof body === 'string'
        ? body
        : (body?.message || '')
      const raw = (fromBody || response.statusText || '').trim()
      const msg = !raw || /^forbidden$/i.test(raw)
        ? 'You are not allowed to do that.'
        : raw

      toast.error(msg)
    },
  })
}
