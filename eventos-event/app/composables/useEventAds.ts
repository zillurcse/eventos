import { useApi } from '~/composables/useApi'
import { eventIdentityHeaders, useEventIdentity } from '~/composables/useEventSubdomain'
import type { ReceptionAd } from '~/stores/reception'

export interface PageAds {
  strip: ReceptionAd[]
  sidebar: ReceptionAd[]
}

/**
 * The organizer's ads (AD Managements) targeted at a given app page for the
 * signed-in visitor. Goes through useApi() so the bearer token rides along and
 * the API can honour Targeted Groups (the visitor's role); Targeted Pages are
 * filtered by `page`. Decorative — resolves to empty on any failure.
 */
export async function fetchPageAds(page: string): Promise<PageAds> {
  const identity = useEventIdentity()
  if (!(identity.subdomain || identity.host)) return { strip: [], sidebar: [] }

  try {
    const api = useApi()
    const res = await api<{ data: PageAds }>('/public/ads', { query: { page } })
    return { strip: res.data?.strip ?? [], sidebar: res.data?.sidebar ?? [] }
  } catch {
    return { strip: [], sidebar: [] }
  }
}

/**
 * Record an impression or click for the Insights dashboard. Fire-and-forget:
 * an ad that fails to report a view must never disturb the page.
 */
export function trackAd(id: number | string, type: 'impression' | 'click'): void {
  const n = Number(id)
  if (!Number.isFinite(n)) return

  const { public: { apiBase } } = useRuntimeConfig()
  $fetch(`${apiBase}/public/ads/${n}/track`, {
    method: 'POST',
    headers: eventIdentityHeaders(),
    body: { type },
  }).catch(() => {})
}
