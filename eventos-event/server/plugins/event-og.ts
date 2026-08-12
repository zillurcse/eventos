/**
 * Per-event crawler meta (favicon + Open Graph / Twitter card), injected
 * server-side into the document HTML.
 *
 * eventos-event is a pure SPA (ssr:false): the browser resolves its event and
 * paints the title/favicon in JS (stores/site.ts applyBranding). But link
 * crawlers — WhatsApp, Facebook, LinkedIn, Slack, Google — never run JS, so
 * they only ever saw Nuxt's default favicon and no share card.
 *
 * This runs at document-render time (before any JS), resolves the event from
 * the request Host exactly the way the client does (useEventSubdomain), fetches
 * that event's public SEO from the API, and writes the tags straight into
 * <head>. Real browsers still get the same tags, so shares work whether or not
 * the SPA has booted yet.
 *
 * Best-effort: any failure (unknown host, API down) leaves the default head
 * untouched rather than breaking the page.
 */
export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('render:html', async (html, { event }) => {
    try {
      const config = useRuntimeConfig(event)
      const base = String(config.public.eventBaseDomain || 'expouse.test').toLowerCase()

      // Host header first (real shares); ?subdomain=/?host= support local dev.
      const url = getRequestURL(event)
      const hostHeader = (getRequestHeader(event, 'host') || '').toLowerCase().split(':')[0]
      const identity = resolveIdentity(hostHeader, base, url.searchParams)
      if (!identity.subdomain && !identity.host) return

      const apiBase = String(config.apiInternalBase || config.public.apiBase || '').replace(/\/$/, '')
      if (!apiBase) return

      const headers: Record<string, string> = {}
      if (identity.subdomain) headers['X-Event-Subdomain'] = identity.subdomain
      if (identity.host) headers['X-Event-Host'] = identity.host

      const res = await $fetch<{ data: SitePayload }>(`${apiBase}/public/site`, {
        headers,
        // A crawler shouldn't wait — bail fast and leave the default head.
        timeout: 2500,
      }).catch(() => null)

      const site = res?.data
      if (!site) return

      const title = site.seo?.meta_title || site.event?.name || ''
      const description = site.seo?.meta_description || site.event?.description || ''
      const image = site.seo?.og_image_url || site.event?.cover_url || site.branding?.logo_url || ''
      const favicon = site.seo?.favicon_url || ''
      const pageUrl = url.href

      const tags: string[] = []

      if (title) {
        // Replace any placeholder <title> Nuxt emitted so crawlers read ours.
        html.head = html.head.map(chunk => chunk.replace(/<title>.*?<\/title>/i, ''))
        tags.push(`<title>${esc(title)}</title>`)
        tags.push(meta('property', 'og:title', title))
        tags.push(meta('name', 'twitter:title', title))
      }
      if (description) {
        tags.push(meta('name', 'description', description))
        tags.push(meta('property', 'og:description', description))
        tags.push(meta('name', 'twitter:description', description))
      }
      if (image) {
        tags.push(meta('property', 'og:image', image))
        tags.push(meta('name', 'twitter:image', image))
      }
      tags.push(meta('property', 'og:type', 'website'))
      tags.push(meta('property', 'og:url', pageUrl))
      if (title) tags.push(meta('property', 'og:site_name', title))
      tags.push(meta('name', 'twitter:card', image ? 'summary_large_image' : 'summary'))

      if (favicon) {
        // Drop Nuxt's default <link rel="icon" href="/favicon.png"> so crawlers
        // and browsers don't have two competing icons to choose between.
        html.head = html.head.map(chunk =>
          chunk.replace(/<link\b[^>]*\brel=["']?(?:shortcut )?icon["']?[^>]*>/gi, ''))
        const type = faviconType(favicon)
        tags.push(`<link rel="icon"${type} href="${esc(favicon)}">`)
        tags.push(`<link rel="shortcut icon"${type} href="${esc(favicon)}">`)
        tags.push(`<link rel="apple-touch-icon" href="${esc(favicon)}">`)
      }

      html.head.push(tags.join(''))
    } catch {
      // Never let SEO enrichment break the document.
    }
  })
})

interface SitePayload {
  event?: { name?: string, description?: string, cover_url?: string | null }
  branding?: { logo_url?: string | null }
  seo?: {
    meta_title?: string | null
    meta_description?: string | null
    og_image_url?: string | null
    favicon_url?: string | null
  }
}

/** Server-side twin of app/composables/useEventSubdomain.ts (Host-based). */
function resolveIdentity(host: string, base: string, params: URLSearchParams): { subdomain: string | null, host: string | null } {
  // Platform subdomain: strip trailing ".<base>" and take the first label.
  if (host && host !== base && host.endsWith('.' + base)) {
    const label = host.slice(0, -(base.length + 1)).split('.')[0]
    if (label && label !== 'www') return { subdomain: label, host: null }
  }

  const isDev = host === 'localhost' || host === '127.0.0.1'
    || host.endsWith('.localhost') || /^\d{1,3}(\.\d{1,3}){3}$/.test(host)

  // Custom domain: any non-dev host that isn't the bare apex.
  if (host && !isDev && host !== base && host !== `www.${base}`) {
    return { subdomain: null, host }
  }

  // Dev fallbacks (?host= / ?subdomain=) so local shares can be previewed.
  const qHost = params.get('host')?.trim().toLowerCase()
  if (qHost) return { subdomain: null, host: qHost }
  const qSub = params.get('subdomain')?.trim().toLowerCase()
  if (qSub) return { subdomain: qSub, host: null }

  return { subdomain: null, host: null }
}

function faviconType(href: string): string {
  const clean = href.split('?')[0].toLowerCase()
  if (clean.endsWith('.png')) return ' type="image/png"'
  if (clean.endsWith('.svg')) return ' type="image/svg+xml"'
  if (clean.endsWith('.ico')) return ' type="image/x-icon"'
  return ''
}

function meta(attr: 'name' | 'property', key: string, content: string): string {
  return `<meta ${attr}="${key}" content="${esc(content)}">`
}

function esc(value: string): string {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}
