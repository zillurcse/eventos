/**
 * Badge design → CSS, merged with one person's data.
 *
 * ── Why this file exists twice ───────────────────────────────────────────────
 * This is a faithful port of the badge editor's own preview renderer
 * (`eventos-admin/modules/badge.expouse/app/components/PreviewCanvas.vue`),
 * which cannot be imported here: it reads the editor's Pinia stores directly and
 * lives inside the admin app. An identical copy sits in the admin at
 * `modules/expouse-admin/event/runtime/utils/badgeDesign.ts`.
 *
 * The rule this file exists to enforce: **an attendee's badge must look exactly
 * like the design the organizer approved.** Every default and every formula
 * below is copied from PreviewCanvas rather than chosen — including ones that
 * look arbitrary, because "arbitrary but identical" is the whole point. If you
 * change a number here, change it in PreviewCanvas and in the admin copy, or
 * printed badges stop matching the editor.
 *
 * The one thing added on top is the merge: a box carries a `key` (the editor's
 * drop handler copies it off the element-library item), and when render data is
 * supplied, a box whose key is one of those tokens draws that person's value in
 * place of the authored placeholder.
 */

/** Types PreviewCanvas renders as text. Note: no h5 — matching it deliberately. */
export const BADGE_TEXT_TYPES = ['h1', 'h2', 'h3', 'h4', 'h6', 'p', 'a', 'span']

export type BadgeData = Record<string, string> | null | undefined

/** `properties.avatar` is `[]` on non-avatar boxes, so never assume an object. */
function obj(v: any): Record<string, any> {
  return v && typeof v === 'object' && !Array.isArray(v) ? v : {}
}

/** CSS's fixed 96dpi: 1mm = 96/25.4 px. 105mm ≈ 397px. */
const PX_PER_MM = 96 / 25.4

/**
 * The canvas the design was authored on, in px.
 *
 * Derived from the millimetre preset, *not* from `page_config.pageWidth` —
 * which cannot be trusted. The editor's page store initialises pageWidth to
 * 105/148 (millimetres in a field that is meant to hold pixels) and only
 * converts to px inside saveBadgeConfig(), so any design saved without opening
 * the badge-size modal persists a 105 × 148 "pixel" canvas. Boxes are still
 * positioned in px against a ~397px page, so scaling to that canvas pushes
 * every element outside the badge and renders a blank card.
 *
 * Millimetres are the reliable half of the pair because the editor lays its
 * page out in mm (`width: ${presetWidth}mm` in preview-badge.vue) and the
 * browser resolves that at the same 96dpi — so this returns the canvas the
 * boxes were actually dragged around on.
 */
export function badgePageSize(badgeJson: any) {
  const cfg = badgeJson?.page_config ?? {}
  const widthMm = Number(cfg.presetWidth) || 105
  const heightMm = Number(cfg.presetHeight) || 148

  return {
    width: Math.round(widthMm * PX_PER_MM),
    height: Math.round(heightMm * PX_PER_MM),
    widthMm,
    heightMm,
  }
}

export function badgeBoxes(badgeJson: any, side: 'front' | 'back'): any[] {
  const list = side === 'back' ? badgeJson?.backBoxes : badgeJson?.frontBoxes
  // Truthy `visible`, as PreviewCanvas tests it — a box with no `visible` key is
  // hidden in the editor, so it must be hidden here too.
  return (Array.isArray(list) ? list : []).filter(b => b?.visible)
}

export function badgeBackground(badgeJson: any, side: 'front' | 'back'): string {
  return (side === 'back' ? badgeJson?.backBackground : badgeJson?.frontBackground) || 'white'
}

/** Punch-hole guides drawn over the canvas. */
export function badgePunch(badgeJson: any) {
  return {
    long: badgeJson?.punchLong ?? null,
    circle: badgeJson?.punchCircle ?? null,
  }
}

/**
 * Box keys that no longer match the token vocabulary.
 *
 * The element library used to offer Full Name under the key `name`; it was
 * renamed to `full_name` (BadgeRenderData::KEYS) after designs had already been
 * saved with the old key. A box carrying a retired key is still a request for
 * that person's name, so it is translated rather than left to print the literal
 * placeholder "Full Name" on everybody's badge.
 */
const KEY_ALIASES: Record<string, string> = {
  name: 'full_name',
}

/** The token a box asks for, after retired keys are translated. */
export function badgeKey(box: any): string | undefined {
  const key = box?.key
  if (!key) return undefined
  return KEY_ALIASES[key] ?? key
}

/** The merged value for a box, or undefined when nothing overrides it. */
function merged(box: any, data: BadgeData): string | undefined {
  const key = badgeKey(box)
  if (!data || !key) return undefined
  return data[key]
}

/** Text to draw: this person's value when there is one, else the design's. */
export function badgeText(box: any, data?: BadgeData): string {
  const value = merged(box, data)
  // An empty merge value means "no company" — print nothing, not the word
  // "Company".
  return value !== undefined ? value : (box?.text ?? box?.properties?.text ?? '')
}

/**
 * Image URL for an img/background/avatar box. Uploaded artwork lives at
 * `properties.src.url`; merge-driven images (photo, event logo) arrive as a bare
 * URL in `text`, which is the shape the editor writes for `event_logo`.
 */
export function badgeImage(box: any, data?: BadgeData): string {
  const value = merged(box, data)
  // Truthiness, not `!== undefined`: an attendee with no photo and an event
  // with no logo both merge to '', and falling through to the artwork the
  // designer placed beats leaving a hole in the badge.
  if (value) return value
  const src = box?.properties?.src?.url
  if (src) return src
  return typeof box?.text === 'string' && box.text.startsWith('http') ? box.text : ''
}

/** QR options. The code always encodes the person, whatever the design says. */
export function badgeQr(box: any, data?: BadgeData) {
  const qr = obj(box?.properties?.qrcode)
  return {
    value: data?.qrcode || merged(box, data) || qr.value || box?.text || 'preview',
    variant: qr.variant && qr.variant !== 'defualt' ? qr.variant : undefined, // sic: editor typo
    radius: qr.radius ?? 0,
    blackColor: qr.blackColor || '#000000',
    whiteColor: qr.whiteColor || 'transparent',
  }
}

/** The absolutely-positioned wrapper every element sits in. */
export function badgeBoxStyle(box: any): Record<string, any> {
  const p = box?.properties ?? {}
  return {
    position: 'absolute',
    top: `${box?.position?.top ?? p.y ?? 0}px`,
    left: `${box?.position?.left ?? p.x ?? 0}px`,
    width: `${p.size?.width ?? 0}px`,
    height: `${p.size?.height ?? 0}px`,
    transform: `rotate(${p.rotation ?? 0}deg)`,
    transformOrigin: 'center center',
    // Note the white default: a non-transparent box with no fill is white in the
    // editor, not see-through.
    backgroundColor: p.fillTransparency ? 'transparent' : (p.fillColor || '#ffffff'),
    border: p.strokeWidth > 0 ? `${p.strokeWidth}px solid ${p.strokeColor}` : 'none',
    zIndex: box?.zIndex || 0,
    overflow: 'hidden',
  }
}

const VERTICAL_FLEX: Record<string, string> = { top: 'flex-start', middle: 'center', bottom: 'flex-end' }
const HORIZONTAL_FLEX: Record<string, string> = { left: 'flex-start', center: 'center', right: 'flex-end' }
const HORIZONTAL_TEXT: Record<string, string> = { left: 'left', center: 'center', right: 'right', justify: 'justify' }

/** CSS font-family with a guaranteed generic fallback. */
function badgeFontFamily(font: unknown): string {
  const raw = typeof font === 'string' ? font.trim() : ''
  if (!raw) return 'Poppins, sans-serif'
  if (/serif|monospace|cursive|fantasy|system-ui/i.test(raw)) return raw
  if (raw.includes(',')) return raw
  return `${raw}, sans-serif`
}

/**
 * Text styling, including the alignment model — which differs by type in the
 * editor and must here too: a `p` is a *column* aligned vertically by
 * justify-content and horizontally by text-align, while every other text type is
 * a *row* aligned by align-items / justify-content with no text-align at all.
 */
export function badgeTextStyle(box: any): Record<string, any> {
  const p = box?.properties ?? {}
  const isParagraph = box?.type === 'p'

  // The editor's "Auto" size: a fraction of the box height, clamped 12–48.
  const calculated = Math.max(
    12,
    Math.min(48, (p.size?.height ?? 0) * (isParagraph ? 0.2 : 0.4)),
  )

  const base: Record<string, any> = {
    width: '100%',
    height: '100%',
    display: 'flex',
    lineHeight: 1.25, // Tailwind's leading-tight
    margin: 0,
    fontSize: (p.fontSize === 'Auto' || !p.fontSize) ? `${calculated}px` : `${p.fontSize}px`,
    // Designs often store a bare family ("Roboto"). Without a fallback, missing
    // webfonts make <h1> fall back to Times on Windows — the serif look on My Badges.
    fontFamily: badgeFontFamily(p.font),
    fontWeight: p.fontWeight || 'normal',
    fontStyle: p.fontStyle || 'normal',
    textDecoration: p.textDecoration || 'none',
    textTransform: p.textTransform || 'none',
    color: p.color || 'black',
    direction: p.direction || 'ltr',
  }

  if (isParagraph) {
    return {
      ...base,
      flexDirection: 'column',
      whiteSpace: 'normal',
      overflowWrap: 'break-word',
      justifyContent: VERTICAL_FLEX[p.verticalAlign] ?? 'center',
      textAlign: HORIZONTAL_TEXT[p.horizontalAlign] ?? 'center',
    }
  }

  return {
    ...base,
    flexDirection: 'row',
    alignItems: VERTICAL_FLEX[p.verticalAlign] ?? 'center',
    justifyContent: HORIZONTAL_FLEX[p.horizontalAlign] ?? 'center',
  }
}

const OBJECT_POSITION: Record<string, string> = {
  'top-left': 'top left', 'top': 'top', 'top-right': 'top right',
  'left': 'left', 'center': 'center', 'right': 'right',
  'bottom-left': 'bottom left', 'bottom': 'bottom', 'bottom-right': 'bottom right',
}

const OBJECT_FIT = ['contain', 'cover', 'fill', 'none', 'scale-down']

/** img / background boxes. */
export function badgeImageStyle(box: any): Record<string, any> {
  const p = box?.properties ?? {}
  const fit = OBJECT_FIT.includes(p.objectFit) ? p.objectFit : undefined
  return {
    width: '100%',
    height: '100%',
    objectPosition: OBJECT_POSITION[p.imagePosition || p.objectFit] ?? undefined,
    objectFit: fit,
  }
}

/**
 * Avatar boxes are a framed container around a cover-fitted image. The editor
 * usually persists raw style objects (`avatar.containerStyle` / `imageStyle`);
 * seeded templates only store `shape` + `radius`, so we derive the frame when
 * those style objects are absent — otherwise every starter logo renders as a
 * sharp rectangle instead of the rounded tile the template promised.
 */
export function badgeAvatarStyle(box: any) {
  const avatar = obj(box?.properties?.avatar)
  const persisted = obj(avatar.containerStyle)
  const derived = Object.keys(persisted).length ? {} : avatarFrameStyle(avatar)

  return {
    container: {
      width: '100%',
      height: '100%',
      overflow: 'hidden',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      backgroundColor: '#f3f4f6',
      ...(avatar.showBorder ? { border: '1px solid #d1d5db' } : {}),
      ...(avatar.showRing ? { boxShadow: '0 0 0 2px #fff, 0 0 0 4px #9ca3af' } : {}),
      ...derived,
      ...persisted,
    },
    image: {
      width: '100%',
      height: '100%',
      objectFit: 'cover',
      ...obj(avatar.imageStyle),
    },
  }
}

/** Mirror of useCanvasStore.computeContainerStyle for shape + radius. */
function avatarFrameStyle(avatar: Record<string, any>): Record<string, any> {
  const shape = avatar.shape || 'rounded'
  const radius = Number(avatar.radius) || 14
  const custom = avatar.customClipPath

  switch (shape) {
    case 'circle':
      return { borderRadius: '9999px' }
    case 'rounded':
      return { borderRadius: `${radius}px` }
    case 'squircle':
      return {
        borderRadius: `${Math.min(radius, 100)}% / ${Math.min(radius + 10, 100)}%`,
      }
    case 'diamond':
      return { clipPath: 'polygon(50% 0, 100% 50%, 50% 100%, 0 50%)' }
    case 'hex':
      return { clipPath: 'polygon(25% 5%, 75% 5%, 100% 50%, 75% 95%, 25% 95%, 0 50%)' }
    case 'triangle':
      return { clipPath: 'polygon(50% 0, 0 100%, 100% 100%)' }
    case 'blob':
      return {
        clipPath:
          'path("M74.7 12.9c11.8 7.3 20.2 20 23 34.2 2.7 14.2-.3 29.9-8.6 39.8-8.3 9.9-21.8 14-35.6 12.2-13.8-1.7-27.8-10.3-35.1-22.8-7.3-12.5-7.8-28.8-2.5-41.4 5.3-12.6 16.4-21.5 28.4-25C56.2 6.6 68.9 5.7 74.7 12.9z")',
      }
    case 'custom':
      return custom ? { clipPath: custom } : { borderRadius: `${radius}px` }
    default:
      return { borderRadius: `${radius}px` }
  }
}
