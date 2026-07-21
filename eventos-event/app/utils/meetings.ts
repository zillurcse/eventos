import type { Meeting } from '~/stores/meetings'

const DEFAULT_DURATION_MS = 30 * 60_000

function slotRange(date: string, slot: string): [number, number] | null {
  const [from, to] = slot.split('-')
  if (!from || !to) return null
  const start = Date.parse(`${date}T${from.trim()}:00`)
  const end = Date.parse(`${date}T${to.trim()}:00`)
  if (Number.isNaN(start) || Number.isNaN(end)) return null
  return [start, end]
}

export function meetingEndMs(m: Meeting): number | null {
  if (m.date && m.slot) {
    const range = slotRange(m.date, m.slot)
    if (range) return range[1]
  }
  if (!m.starts_at) return null
  const start = new Date(m.starts_at).getTime()
  return m.ends_at ? new Date(m.ends_at).getTime() : start + DEFAULT_DURATION_MS
}

function fmtTime(ms: number, tz?: string) {
  return new Intl.DateTimeFormat('en-US', {
    hour: 'numeric', minute: '2-digit', hour12: true, timeZone: tz,
  }).format(new Date(ms))
}

function fmtDay(ms: number, tz?: string) {
  return new Intl.DateTimeFormat('en-US', {
    weekday: 'short', month: 'short', day: 'numeric', timeZone: tz,
  }).format(new Date(ms))
}

export function meetingTimeLabel(m: Meeting, tz?: string): string {
  let start: number | null = null
  let end: number | null = null

  if (m.date && m.slot) {
    const range = slotRange(m.date, m.slot)
    if (range) [start, end] = range
  }
  if (start === null && m.starts_at) {
    start = new Date(m.starts_at).getTime()
    end = m.ends_at ? new Date(m.ends_at).getTime() : null
  }
  if (start === null || Number.isNaN(start)) return 'Time to be arranged'
  if (end !== null && Number.isNaN(end)) end = null

  const dayOf = (ms: number) => new Intl.DateTimeFormat('en-CA', {
    timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit',
  }).format(new Date(ms))

  const range = end === null ? fmtTime(start, tz) : `${fmtTime(start, tz)} - ${fmtTime(end, tz)}`
  return dayOf(start) === dayOf(Date.now()) ? range : `${fmtDay(start, tz)} · ${range}`
}
