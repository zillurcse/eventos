/**
 * Resolve which event this microsite is for, from the browser hostname.
 *
 * Production: the SPA is served at <subdomain>.<apex> (e.g. edu.expouse.test),
 * so the leading label — everything before the configured base domain — IS the
 * event subdomain.
 *
 * Local dev: on localhost / an IP / a bare apex there's no subdomain label, so
 * we fall back to a ?subdomain= query param (persisted to sessionStorage for
 * the tab) — visit http://localhost:3001/?subdomain=expo1234.
 *
 * Returns null when nothing resolves (the site store then shows "not found").
 */
export function useEventSubdomain(): string | null {
  if (!import.meta.client) return null

  const { public: { eventBaseDomain } } = useRuntimeConfig()
  const base = String(eventBaseDomain || 'expouse.test').toLowerCase()
  const host = window.location.hostname.toLowerCase()

  // Hostname-derived subdomain: strip a trailing ".<base>" and take the first label.
  if (host !== base && host.endsWith('.' + base)) {
    const label = host.slice(0, -(base.length + 1)).split('.')[0]
    if (label && label !== 'www') return label
  }

  // Dev fallback: ?subdomain=… (sticky for the tab so later navigations keep it).
  const key = 'eventos_subdomain'
  const fromQuery = new URLSearchParams(window.location.search).get('subdomain')
  if (fromQuery) {
    const v = fromQuery.trim().toLowerCase()
    if (v) {
      sessionStorage.setItem(key, v)
      return v
    }
  }

  return sessionStorage.getItem(key)
}
