/**
 * Formats UTC instants against an explicit IANA zone (e.g. the event's own
 * timezone) instead of the organizer's browser timezone, and extracts the
 * wall-clock date/time an <input type="date|time"> needs to round-trip a
 * value without drifting when saved back.
 */

export function tzDateInput(iso: string | null, tz: string): string {
  if (!iso) return ''
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit',
  }).format(new Date(iso))
}

export function tzTimeInput(iso: string | null, tz: string): string {
  if (!iso) return ''
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: tz, hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(new Date(iso))
  const h = parts.find(p => p.type === 'hour')?.value ?? '00'
  const m = parts.find(p => p.type === 'minute')?.value ?? '00'
  return `${h}:${m}`
}

export function tzTimeLabel(iso: string | null, tz: string): string {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', timeZone: tz })
}

export function tzDateLabel(iso: string | null, tz: string): string {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString([], {
    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', timeZone: tz,
  })
}
