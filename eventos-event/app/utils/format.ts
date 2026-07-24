/**
 * Shared display formatters (auto-imported from app/utils).
 *
 * `initials` lives in utils/avatar now, next to the colour it is drawn on —
 * see <UserAvatar> for people, <AppImage> for everything else.
 */

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

/** A single moment, in the viewer's own timezone: "Jul 24, 3:00 PM". */
export function contestWhen(iso?: string | null): string {
  if (!iso) return ''
  return new Date(iso).toLocaleString('en-US', {
    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
  })
}

/** The contest's open window, collapsed to one line when it starts and ends the same day. */
export function contestWindow(startsAt?: string | null, endsAt?: string | null): string {
  if (!startsAt && !endsAt) return ''
  if (!startsAt) return `Ends ${contestWhen(endsAt)}`
  if (!endsAt) return `Starts ${contestWhen(startsAt)}`

  const start = new Date(startsAt)
  const end = new Date(endsAt)
  const sameDay = start.toDateString() === end.toDateString()
  const endText = sameDay
    ? end.toLocaleString('en-US', { hour: 'numeric', minute: '2-digit' })
    : contestWhen(endsAt)

  return `${contestWhen(startsAt)} – ${endText}`
}

/** Days/hours/minutes remaining until a deadline, for a boxed countdown. Null once it has passed. */
export function contestCountdown(iso?: string | null, now = Date.now()): { days: number, hours: number, mins: number } | null {
  if (!iso) return null
  const ms = new Date(iso).getTime() - now
  if (ms <= 0) return null
  const mins = Math.floor(ms / 60000)
  return { days: Math.floor(mins / 1440), hours: Math.floor(mins / 60) % 24, mins: mins % 60 }
}

/** Time left until a deadline: "2d left", "4h left", "12m left", then "Closing". */
export function timeLeft(iso?: string | null): string {
  if (!iso) return ''
  const s = Math.floor((new Date(iso).getTime() - Date.now()) / 1000)
  if (s <= 0) return 'Closed'
  const m = Math.floor(s / 60)
  if (m < 1) return 'Closing'
  if (m < 60) return `${m}m left`
  const h = Math.floor(m / 60)
  if (h < 24) return `${h}h left`
  return `${Math.floor(h / 24)}d left`
}
