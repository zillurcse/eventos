/**
 * Shared display formatters (auto-imported from app/utils). Several components
 * still carry local copies of `initials` — those shadow this one harmlessly;
 * new code should rely on these.
 */

/** "Jane van Dorn" → "JV" — avatar fallback. */
export function initials(name?: string | null): string {
  const p = (name || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

/** Relative timestamp: "just now", "5m ago", "3h ago", "2d ago", then a date. */
export function timeAgo(iso?: string | null): string {
  if (!iso) return ''
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (s < 60) return 'just now'
  const m = Math.floor(s / 60)
  if (m < 60) return `${m}m ago`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h}h ago`
  const d = Math.floor(h / 24)
  if (d < 7) return `${d}d ago`
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
