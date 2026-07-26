/**
 * Resolve which event this microsite is for, from the browser hostname.
 *
 * Production platform: SPA at <subdomain>.<apex> → X-Event-Subdomain
 * Production custom:  SPA at events.company.com   → X-Event-Host (verified)
 * Local dev:          ?subdomain=… or ?host=… (sticky in sessionStorage)
 */

export interface EventIdentity {
  /** Platform subdomain label (expo1234), when on *.apex or ?subdomain= */
  subdomain: string | null
  /** Full custom hostname when not on the platform apex */
  host: string | null
}

function isDevHost(hostname: string): boolean {
  return hostname === 'localhost'
    || hostname === '127.0.0.1'
    || hostname.endsWith('.localhost')
    || /^\d{1,3}(\.\d{1,3}){3}$/.test(hostname)
}

export function useEventIdentity(): EventIdentity {
  if (!import.meta.client) return { subdomain: null, host: null }

  const { public: { eventBaseDomain } } = useRuntimeConfig()
  const base = String(eventBaseDomain || 'expouse.test').toLowerCase()
  const hostname = window.location.hostname.toLowerCase()

  // Platform subdomain: strip trailing ".<base>" and take the first label.
  if (hostname !== base && hostname.endsWith('.' + base)) {
    const label = hostname.slice(0, -(base.length + 1)).split('.')[0]
    if (label && label !== 'www') {
      return { subdomain: label, host: null }
    }
  }

  // Custom domain: any non-dev host that isn't the bare apex.
  if (!isDevHost(hostname) && hostname !== base && hostname !== `www.${base}`) {
    return { subdomain: null, host: hostname }
  }

  // Dev fallbacks (sticky for the tab).
  const params = new URLSearchParams(window.location.search)
  const subKey = 'eventos_subdomain'
  const hostKey = 'eventos_host'

  const fromHostQuery = params.get('host')
  if (fromHostQuery) {
    const v = fromHostQuery.trim().toLowerCase()
    if (v) {
      sessionStorage.setItem(hostKey, v)
      sessionStorage.removeItem(subKey)
      return { subdomain: null, host: v }
    }
  }

  const fromSubQuery = params.get('subdomain')
  if (fromSubQuery) {
    const v = fromSubQuery.trim().toLowerCase()
    if (v) {
      sessionStorage.setItem(subKey, v)
      sessionStorage.removeItem(hostKey)
      return { subdomain: v, host: null }
    }
  }

  const stickyHost = sessionStorage.getItem(hostKey)
  if (stickyHost) return { subdomain: null, host: stickyHost }

  const stickySub = sessionStorage.getItem(subKey)
  if (stickySub) return { subdomain: stickySub, host: null }

  return { subdomain: null, host: null }
}

/** Headers every public/event API call needs for host → event resolution. */
export function eventIdentityHeaders(): Record<string, string> {
  const { subdomain, host } = useEventIdentity()
  const headers: Record<string, string> = {}
  if (subdomain) headers['X-Event-Subdomain'] = subdomain
  if (host) headers['X-Event-Host'] = host
  return headers
}

/**
 * Back-compat: platform subdomain only (null on a custom domain).
 * Prefer useEventIdentity() / eventIdentityHeaders() for new code.
 */
export function useEventSubdomain(): string | null {
  return useEventIdentity().subdomain
}
