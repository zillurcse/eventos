/**
 * Generated avatars for people — what we show when a person has no photo.
 *
 * A *person* without a photo gets initials on a coloured disc; anything else
 * without an image (a logo, a room poster, a table shot) gets the event's
 * placeholder graphic instead — see <AppImage>. The two are not
 * interchangeable: a stock graphic in a delegate list tells you nothing about
 * who is in it, and initials in place of a room poster look like a bug.
 *
 * Generated here rather than fetched from ui-avatars.com (or any other avatar
 * service): a remote avatar would put every attendee's *name* in a URL to a
 * third party, add a network dependency on venue wifi, and rate-limit a page
 * that renders 200 discs at once. This is the same picture, made locally.
 *
 * The colour comes from the event's own theme (site.branding.primary, the same
 * value painted onto --brand-primary), so a generated avatar always belongs to
 * the event it appears in. It is not *exactly* the theme colour, though: a
 * delegate list where all 200 fallbacks are the identical disc is a wall of
 * violet with no way to tell one person from another. The name is hashed into a
 * bounded hue/lightness shift around the theme colour — recognisably on-brand,
 * individually distinguishable, and stable (the same person is the same colour
 * on every screen, every session).
 */

/** "Jane van Dorn" → "JD". Last word, not the second: the family name is what
 *  people recognise, so "Grace B. Hopper" reads GH rather than GB. */
export function initials(name?: string | null): string {
  const parts = (name || '').trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '?'

  const first = parts[0]![0] ?? ''
  const last = parts.length > 1 ? (parts[parts.length - 1]![0] ?? '') : ''

  return (first + last).toUpperCase() || '?'
}

/** Stable 32-bit hash — the same name must always land on the same colour. */
function hash(text: string): number {
  let h = 0
  for (let i = 0; i < text.length; i++) {
    h = (h << 5) - h + text.charCodeAt(i)
    h |= 0
  }
  return Math.abs(h)
}

interface Hsl { h: number, s: number, l: number }

function hexToHsl(hex: string): Hsl | null {
  const m = /^#?([\da-f]{3}|[\da-f]{6})$/i.exec(hex.trim())
  if (!m) return null

  let raw = m[1]!
  if (raw.length === 3) raw = raw.split('').map(c => c + c).join('')

  const r = parseInt(raw.slice(0, 2), 16) / 255
  const g = parseInt(raw.slice(2, 4), 16) / 255
  const b = parseInt(raw.slice(4, 6), 16) / 255

  const max = Math.max(r, g, b)
  const min = Math.min(r, g, b)
  const l = (max + min) / 2
  const d = max - min

  if (!d) return { h: 0, s: 0, l: l * 100 }

  const s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
  let h: number
  if (max === r) h = ((g - b) / d + (g < b ? 6 : 0)) / 6
  else if (max === g) h = ((b - r) / d + 2) / 6
  else h = ((r - g) / d + 4) / 6

  return { h: h * 360, s: s * 100, l: l * 100 }
}

/** The event's theme colour, or the platform violet before the site has loaded. */
const FALLBACK_PRIMARY = '#6352e7'

/**
 * The disc colour for a person, in the event's palette.
 *
 * Hue moves within ±22° of the theme — enough to separate neighbours in a list,
 * not enough to leave the brand family. Lightness is clamped to 38–52%, which
 * keeps the white initials above the WCAG AA contrast floor no matter what
 * colour the organizer picked (a pastel theme would otherwise generate
 * white-on-yellow).
 */
export function avatarColor(name?: string | null, primary?: string | null): string {
  const base = hexToHsl(primary || FALLBACK_PRIMARY) ?? hexToHsl(FALLBACK_PRIMARY)!
  const n = hash((name || '?').trim().toLowerCase())

  const hue = (base.h + (n % 45) - 22 + 360) % 360
  const sat = Math.min(85, Math.max(35, base.s || 60))
  const light = 38 + (n % 15) // 38–52%

  return `hsl(${Math.round(hue)} ${Math.round(sat)}% ${light}%)`
}
