/**
 * Badge design → CSS, merged with one person's data.
 *
 * ── Why this file exists twice ───────────────────────────────────────────────
 * This is a faithful port of the badge editor's own preview renderer
 * (`eventos-admin/modules/badge.expouse/app/components/PreviewCanvas.vue`),
 * which is not reused directly because it reads the editor's Pinia stores rather
 * than props. An identical copy sits in the event app at
 * `eventos-event/app/utils/badgeDesign.ts`.
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

export function badgePageSize(badgeJson: any) {
  const cfg = badgeJson?.page_config ?? {}
  return {
    // 105 × 148 mm at CSS's fixed 96dpi ≈ 397 × 559 px — the units the editor
    // authors box positions in.
    width: Number(cfg.pageWidth) || 397,
    height: Number(cfg.pageHeight) || 559,
    widthMm: Number(cfg.presetWidth) || 105,
    heightMm: Number(cfg.presetHeight) || 148,
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

/** The merged value for a box, or undefined when nothing overrides it. */
function merged(box: any, data: BadgeData): string | undefined {
  if (!data || !box?.key) return undefined
  return data[box.key]
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
  if (value !== undefined) return value
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
    fontFamily: p.font || 'poppins, sans-serif',
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
 * Avatar boxes are a framed container around a cover-fitted image, and the
 * editor persists both halves as raw style objects (`avatar.containerStyle` /
 * `avatar.imageStyle`) — so they are spread verbatim rather than re-derived.
 */
export function badgeAvatarStyle(box: any) {
  const avatar = obj(box?.properties?.avatar)
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
      ...obj(avatar.containerStyle),
    },
    image: {
      width: '100%',
      height: '100%',
      objectFit: 'cover',
      ...obj(avatar.imageStyle),
    },
  }
}
