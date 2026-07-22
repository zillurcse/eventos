/**
 * Block model for the email builder. Blocks are flat objects { id, type, ...props,
 * style } that serialize 1:1 to what the Laravel EmailRenderer consumes. A
 * `columns` block nests child blocks per column. Kept framework-agnostic so the
 * editor, canvas and inspector all share one source of truth.
 */

export type BlockType =
  | 'heading' | 'text' | 'button' | 'image'
  | 'divider' | 'spacer' | 'social' | 'columns' | 'html'
  | 'logo' | 'video'

export interface SocialItem { network: string, url: string }

export interface Block {
  id: string
  type: BlockType
  // props (subset used per type)
  text?: string
  level?: number
  html?: string
  url?: string
  src?: string
  alt?: string
  href?: string
  items?: SocialItem[]
  columns?: Block[][]
  /** Per-column percentages, one per column. Absent = even split. */
  widths?: number[]
  style: Record<string, any>
}

export interface EmailSettings {
  backgroundColor: string
  contentBackground: string
  contentWidth: number
  fontFamily: string
  textColor: string
  linkColor: string
  borderRadius: number
  /** Optional CDN of `<network>.png` icons; falls back to branded chips. */
  socialIconBaseUrl?: string
}

let seq = 0
export function uid(): string {
  seq += 1
  return `b${Date.now().toString(36)}${seq.toString(36)}`
}

export const FONT_STACKS: { label: string, value: string }[] = [
  { label: 'System (modern)', value: "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif" },
  { label: 'Arial', value: 'Arial,Helvetica,sans-serif' },
  { label: 'Georgia (serif)', value: 'Georgia,Times,serif' },
  { label: 'Trebuchet', value: "'Trebuchet MS',Tahoma,sans-serif" },
  { label: 'Courier (mono)', value: "'Courier New',Courier,monospace" },
]

export function defaultSettings(): EmailSettings {
  return {
    backgroundColor: '#f1f5f9',
    contentBackground: '#ffffff',
    contentWidth: 600,
    fontFamily: FONT_STACKS[0]!.value,
    textColor: '#334155',
    linkColor: '#6352e7',
    borderRadius: 12,
  }
}

/** Build a new block of `type` with sensible professional defaults. */
export function createBlock(type: BlockType): Block {
  const base: Block = { id: uid(), type, style: {} }
  switch (type) {
    case 'heading':
      return { ...base, text: 'Your headline goes here', level: 1, style: { align: 'left', color: '#0f172a', fontSize: 28, fontWeight: '700', paddingTop: 18, paddingBottom: 8 } }
    case 'text':
      return { ...base, html: 'Write your message here. Use the toolbar to format text and insert dynamic variables like {{ contact.first_name }}.', style: { align: 'left', fontSize: 15, lineHeight: '1.6', paddingTop: 8, paddingBottom: 8 } }
    case 'button':
      return { ...base, text: 'Get your ticket', url: 'https://', style: { align: 'left', backgroundColor: '#6352e7', color: '#ffffff', borderRadius: 8, paddingX: 26, paddingY: 13, fontSize: 15, fullWidth: false, paddingTop: 8, paddingBottom: 8 } }
    case 'image':
      return { ...base, src: '', alt: '', href: '', style: { align: 'center', width: 100, borderRadius: 8, paddingTop: 8, paddingBottom: 8 } }
    case 'divider':
      return { ...base, style: { color: '#e2e8f0', height: 1, width: 100, paddingTop: 12, paddingBottom: 12 } }
    case 'spacer':
      return { ...base, style: { height: 24, paddingTop: 0, paddingBottom: 0, paddingLeft: 0, paddingRight: 0 } }
    case 'social':
      return { ...base, items: [{ network: 'twitter', url: 'https://' }, { network: 'linkedin', url: 'https://' }, { network: 'instagram', url: 'https://' }], style: { align: 'center', iconSize: 28, color: '#64748b', paddingTop: 12, paddingBottom: 12 } }
    case 'columns':
      return { ...base, columns: [[createBlock('text')], [createBlock('text')]], widths: [50, 50], style: { gap: 16, paddingTop: 8, paddingBottom: 8 } }
    case 'html':
      return { ...base, html: '<!-- Your custom HTML -->', style: { paddingTop: 8, paddingBottom: 8 } }
    case 'logo':
      return { ...base, src: '', alt: 'Logo', href: '', style: { align: 'center', width: 160, paddingTop: 20, paddingBottom: 12, backgroundColor: '#ffffff' } }
    case 'video':
      return { ...base, src: '', url: 'https://', style: { align: 'center', borderRadius: 8, paddingTop: 8, paddingBottom: 8 } }
    default:
      return base
  }
}

export interface PaletteItem { type: BlockType, label: string, icon: string }

/** Left-rail palette. `icon` is an inline SVG path string (24x24, stroked). */
export const PALETTE: PaletteItem[] = [
  { type: 'logo', label: 'Logo', icon: 'M12 2L2 7l10 5 10-5-10-5z M2 17l10 5 10-5 M2 12l10 5 10-5' },
  { type: 'heading', label: 'Heading', icon: 'M6 4v16M18 4v16M6 12h12' },
  { type: 'text', label: 'Text', icon: 'M4 6h16M4 12h16M4 18h11' },
  { type: 'button', label: 'Button', icon: 'M3 9h18v6H3z M7 12h10' },
  { type: 'image', label: 'Image', icon: 'M3 5h18v14H3z M8 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4z M21 17l-5-5-9 7' },
  { type: 'video', label: 'Video', icon: 'M22 8l-6 4 6 4V8z M2 6h14v12H2z' },
  { type: 'columns', label: 'Columns', icon: 'M4 4h7v16H4z M13 4h7v16h-7z' },
  { type: 'divider', label: 'Divider', icon: 'M3 12h18' },
  { type: 'spacer', label: 'Spacer', icon: 'M12 4v16 M8 8l4-4 4 4 M8 16l4 4 4-4' },
  { type: 'social', label: 'Social', icon: 'M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M8.6 13.5l6.8 4 M15.4 6.5l-6.8 4' },
  { type: 'html', label: 'HTML', icon: 'M8 6l-5 6 5 6 M16 6l5 6-5 6' },
]

/**
 * A polished starter template so the canvas is never empty. Mirrors a typical
 * event invitation: hero image, headline, body, CTA, divider, footer.
 */
export function starterBlocks(): Block[] {
  const heading = createBlock('heading')
  heading.text = 'You\'re invited to {{ event.name }}'
  const intro = createBlock('text')
  intro.html = 'Hi {{ contact.first_name }},<br><br>We\'d love to see you at <strong>{{ event.name }}</strong> on {{ event.starts_at }} at {{ event.location }}. Reserve your spot below — seats are limited.'
  const cta = createBlock('button')
  cta.text = 'Register now'
  cta.style.align = 'center'
  cta.style.fullWidth = false
  const divider = createBlock('divider')
  const footer = createBlock('text')
  footer.html = '© {{ system.year }} {{ organization.name }}. You received this because you registered interest in {{ event.name }}.<br><a href="{{ unsubscribe_url }}">Unsubscribe</a>'
  footer.style = { align: 'center', fontSize: 12, color: '#94a3b8', lineHeight: '1.6', paddingTop: 8, paddingBottom: 18 }
  return [heading, intro, cta, divider, footer]
}

/** Walk the block tree (incl. column children) calling fn on every block. */
export function walkBlocks(blocks: Block[], fn: (b: Block) => void) {
  for (const b of blocks) {
    fn(b)
    if (b.type === 'columns' && b.columns) {
      for (const col of b.columns) walkBlocks(col, fn)
    }
  }
}

/**
 * Locate a block by id and return the array it lives in + its index, so the
 * caller can splice/move it. Searches column children too.
 */
export function findContext(blocks: Block[], id: string): { arr: Block[], index: number } | null {
  for (let i = 0; i < blocks.length; i++) {
    const b = blocks[i]!
    if (b.id === id) return { arr: blocks, index: i }
    if (b.type === 'columns' && b.columns) {
      for (const col of b.columns) {
        const found = findContext(col, id)
        if (found) return found
      }
    }
  }
  return null
}

/** Deep-clone a block subtree, assigning fresh ids throughout. */
export function cloneBlock(block: Block): Block {
  const copy: Block = JSON.parse(JSON.stringify(block))
  walkBlocks([copy], b => { b.id = uid() })
  return copy
}

/** Coarse gallery buckets — mirrors EmailTemplate::CATEGORIES on the API. */
export const CATEGORIES: { key: string, label: string }[] = [
  { key: 'invitation', label: 'Invitation' },
  { key: 'reminder', label: 'Reminder' },
  { key: 'confirmation', label: 'Confirmation' },
  { key: 'marketing', label: 'Marketing' },
  { key: 'system', label: 'System' },
  { key: 'custom', label: 'Custom' },
]

/**
 * Resize a columns block, keeping existing content and redistributing widths
 * evenly. Removed columns' children are appended to the last surviving column
 * rather than silently dropped.
 */
export function setColumnCount(block: Block, count: number) {
  const cols = block.columns ?? (block.columns = [])
  const n = Math.max(1, Math.min(4, count))

  while (cols.length < n) cols.push([])
  if (cols.length > n) {
    const removed = cols.splice(n)
    const last = cols[n - 1]!
    for (const col of removed) last.push(...col)
  }

  block.widths = evenWidths(n)
}

/** Percentages summing to exactly 100, with the remainder on the last column. */
export function evenWidths(count: number): number[] {
  const each = Math.floor(100 / count)
  const widths = Array.from({ length: count }, () => each)
  widths[count - 1] = 100 - each * (count - 1)
  return widths
}

/**
 * Adjust one column's width and absorb the difference from the others, so the
 * row always totals 100% and no column collapses below 5%.
 */
export function setColumnWidth(block: Block, index: number, value: number) {
  const count = block.columns?.length ?? 0
  if (!count) return

  const widths = block.widths?.length === count ? [...block.widths] : evenWidths(count)
  const others = count - 1
  if (!others) { block.widths = [100]; return }

  const next = Math.max(5, Math.min(100 - others * 5, Math.round(value)))
  const remainder = 100 - next
  const previousOthers = widths.reduce((sum, w, i) => (i === index ? sum : sum + w), 0)

  block.widths = widths.map((w, i) => {
    if (i === index) return next
    // Keep the other columns' relative proportions while refitting to 100.
    const share = previousOthers > 0 ? w / previousOthers : 1 / others
    return Math.max(5, Math.round(remainder * share))
  })

  // Rounding can drift a point either way; settle it on the last other column.
  const drift = 100 - block.widths.reduce((a, b) => a + b, 0)
  const lastOther = block.widths.length - 1 === index ? block.widths.length - 2 : block.widths.length - 1
  if (drift && lastOther >= 0) block.widths[lastOther]! += drift
}

export interface AuditIssue {
  blockId: string | null
  severity: 'error' | 'warning'
  message: string
}

/**
 * Pre-send checks for the problems that are invisible in the editor but obvious
 * in an inbox: images with no alt text (many clients block images by default),
 * placeholder links that were never filled in, and a missing preheader.
 */
export function auditDesign(
  blocks: Block[],
  opts: { subject?: string, preheader?: string } = {},
): AuditIssue[] {
  const issues: AuditIssue[] = []

  if (!opts.subject?.trim()) {
    issues.push({ blockId: null, severity: 'error', message: 'Subject line is empty.' })
  }
  if (!opts.preheader?.trim()) {
    issues.push({ blockId: null, severity: 'warning', message: 'No preheader — inboxes will preview your first line of copy instead.' })
  }

  walkBlocks(blocks, (b) => {
    if ((b.type === 'image' || b.type === 'video') && b.src && !b.alt?.trim()) {
      issues.push({ blockId: b.id, severity: 'warning', message: `${b.type === 'video' ? 'Video thumbnail' : 'Image'} has no alt text.` })
    }
    if (b.type === 'image' && !b.src) {
      issues.push({ blockId: b.id, severity: 'error', message: 'Image block has no image.' })
    }
    if (b.type === 'logo' && b.src && !b.alt?.trim()) {
      issues.push({ blockId: b.id, severity: 'warning', message: 'Logo has no alt text.' })
    }
    if (b.type === 'button') {
      if (!b.text?.trim()) issues.push({ blockId: b.id, severity: 'error', message: 'Button has no label.' })
      if (!b.url || /^https?:\/\/$/.test(b.url.trim()) || b.url.trim() === '#') {
        issues.push({ blockId: b.id, severity: 'error', message: `Button "${b.text || 'Untitled'}" has a placeholder link.` })
      }
    }
    if (b.type === 'social') {
      const unset = (b.items ?? []).filter(i => !i.url || /^https?:\/\/$/.test(i.url.trim()))
      if (unset.length) {
        issues.push({ blockId: b.id, severity: 'warning', message: `${unset.length} social link${unset.length > 1 ? 's are' : ' is'} still a placeholder.` })
      }
    }
  })

  return issues
}

export interface TemplatePreset {
  id: string
  name: string
  description: string
  accent: string
  blocks: () => Block[]
  settings: () => Partial<EmailSettings>
}

function mk(type: BlockType, overrides: Partial<Block> = {}): Block {
  return { ...createBlock(type), ...overrides, style: { ...createBlock(type).style, ...(overrides.style ?? {}) } }
}

export const TEMPLATE_PRESETS: TemplatePreset[] = [
  {
    id: 'blank',
    name: 'Blank',
    description: 'Start with a clean canvas',
    accent: '#6352e7',
    blocks: () => [],
    settings: () => defaultSettings(),
  },
  {
    id: 'invitation',
    name: 'Event Invitation',
    description: 'Classic invitation with CTA',
    accent: '#6352e7',
    blocks: () => {
      const logo = createBlock('logo')
      const hero = createBlock('image')
      hero.style = { ...hero.style, width: 100, borderRadius: 0, paddingTop: 0, paddingBottom: 0 }
      const h = createBlock('heading')
      h.text = "You're invited to {{ event.name }}"
      h.style = { ...h.style, align: 'center', fontSize: 30 }
      const body = createBlock('text')
      body.html = 'Hi {{ contact.first_name }},<br><br>We\'d love to see you at <strong>{{ event.name }}</strong> — {{ event.starts_at }} at {{ event.location }}. Reserve your spot before seats run out.'
      body.style = { ...body.style, align: 'center' }
      const cta = createBlock('button')
      cta.text = 'Register now →'
      cta.style = { ...cta.style, align: 'center', fullWidth: false }
      const div = createBlock('divider')
      const social = createBlock('social')
      const footer = createBlock('text')
      footer.html = '© {{ system.year }} {{ organization.name }} · <a href="{{ unsubscribe_url }}">Unsubscribe</a>'
      footer.style = { align: 'center', fontSize: 12, color: '#94a3b8', lineHeight: '1.6', paddingTop: 8, paddingBottom: 18 }
      return [logo, hero, h, body, cta, div, social, footer]
    },
    settings: () => ({ ...defaultSettings(), backgroundColor: '#f1f5f9' }),
  },
  {
    id: 'reminder',
    name: 'Event Reminder',
    description: 'Countdown-style reminder email',
    accent: '#0ea5e9',
    blocks: () => {
      const logo = createBlock('logo')
      const h = createBlock('heading')
      h.text = '⏰ Just {{ days_left }} days until {{ event.name }}'
      h.style = { ...h.style, align: 'center', color: '#0ea5e9', fontSize: 26 }
      const body = createBlock('text')
      body.html = 'Hi {{ contact.first_name }}, your spot is confirmed for <strong>{{ event.name }}</strong> on {{ event.starts_at }}.<br><br>Add it to your calendar and make sure you don\'t miss a moment.'
      body.style = { ...body.style, align: 'center' }
      const cols = createBlock('columns')
      const calBtn = createBlock('button')
      calBtn.text = '📅 Add to Calendar'
      calBtn.style = { ...calBtn.style, backgroundColor: '#0ea5e9', borderRadius: 8, paddingX: 20, paddingY: 11, fontSize: 14 }
      const mapBtn = createBlock('button')
      mapBtn.text = '📍 View Location'
      mapBtn.style = { ...mapBtn.style, backgroundColor: '#64748b', borderRadius: 8, paddingX: 20, paddingY: 11, fontSize: 14 }
      cols.columns = [[calBtn], [mapBtn]]
      const footer = createBlock('text')
      footer.html = '© {{ system.year }} {{ organization.name }} · <a href="{{ unsubscribe_url }}">Unsubscribe</a>'
      footer.style = { align: 'center', fontSize: 12, color: '#94a3b8', lineHeight: '1.6', paddingTop: 8, paddingBottom: 18 }
      return [logo, h, body, cols, createBlock('divider'), footer]
    },
    settings: () => ({ ...defaultSettings(), backgroundColor: '#e0f2fe', linkColor: '#0ea5e9' }),
  },
  {
    id: 'confirmation',
    name: 'Registration Confirmation',
    description: 'Clean confirmation receipt',
    accent: '#16a34a',
    blocks: () => {
      const logo = createBlock('logo')
      const h = createBlock('heading')
      h.text = '✅ You\'re registered!'
      h.style = { ...h.style, align: 'center', color: '#16a34a', fontSize: 28 }
      const body = createBlock('text')
      body.html = 'Hi {{ contact.first_name }},<br><br>Your registration for <strong>{{ event.name }}</strong> is confirmed. Here are your details:'
      body.style = { ...body.style, align: 'center' }
      const details = createBlock('html')
      details.html = `<table width="100%" cellpadding="12" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;font-size:14px">
  <tr><td style="color:#64748b;border-bottom:1px solid #e2e8f0">Event</td><td style="font-weight:600;border-bottom:1px solid #e2e8f0">{{ event.name }}</td></tr>
  <tr><td style="color:#64748b;border-bottom:1px solid #e2e8f0">Date</td><td style="font-weight:600;border-bottom:1px solid #e2e8f0">{{ event.starts_at }}</td></tr>
  <tr><td style="color:#64748b">Location</td><td style="font-weight:600">{{ event.location }}</td></tr>
</table>`
      const cta = createBlock('button')
      cta.text = 'View your ticket'
      cta.style = { ...cta.style, align: 'center', backgroundColor: '#16a34a', fullWidth: false }
      const footer = createBlock('text')
      footer.html = '© {{ system.year }} {{ organization.name }} · <a href="{{ unsubscribe_url }}">Unsubscribe</a>'
      footer.style = { align: 'center', fontSize: 12, color: '#94a3b8', lineHeight: '1.6', paddingTop: 8, paddingBottom: 18 }
      return [logo, h, body, details, cta, createBlock('divider'), footer]
    },
    settings: () => ({ ...defaultSettings(), backgroundColor: '#f0fdf4', linkColor: '#16a34a' }),
  },
  {
    id: 'newsletter',
    name: 'Newsletter',
    description: 'Multi-section digest layout',
    accent: '#f59e0b',
    blocks: () => {
      const logo = createBlock('logo')
      const h = createBlock('heading')
      h.text = '{{ event.name }} — Monthly Update'
      h.style = { ...h.style, align: 'center', color: '#f59e0b', fontSize: 24 }
      const intro = createBlock('text')
      intro.html = 'Welcome to the latest update from <strong>{{ event.name }}</strong>. Here\'s what\'s new this month.'
      const divL = createBlock('divider')
      const col1 = createBlock('columns')
      const img1 = createBlock('image')
      const t1 = createBlock('text')
      t1.html = '<strong>Speaker Spotlight</strong><br>Get to know our keynote speakers and what they\'ll be sharing at the event.'
      col1.columns = [[img1], [t1]]
      const divR = createBlock('divider')
      const col2 = createBlock('columns')
      const t2 = createBlock('text')
      t2.html = '<strong>Agenda Highlights</strong><br>Browse the full schedule and plan your must-attend sessions.'
      const img2 = createBlock('image')
      col2.columns = [[t2], [img2]]
      const social = createBlock('social')
      const footer = createBlock('text')
      footer.html = '© {{ system.year }} {{ organization.name }} · <a href="{{ unsubscribe_url }}">Unsubscribe</a>'
      footer.style = { align: 'center', fontSize: 12, color: '#94a3b8', lineHeight: '1.6', paddingTop: 8, paddingBottom: 18 }
      return [logo, h, intro, divL, col1, divR, col2, createBlock('divider'), social, footer]
    },
    settings: () => ({ ...defaultSettings(), backgroundColor: '#fffbeb', linkColor: '#f59e0b' }),
  },
  {
    id: 'announcement',
    name: 'Announcement',
    description: 'Bold speaker or session reveal',
    accent: '#7c3aed',
    blocks: () => {
      const logo = createBlock('logo')
      const hero = createBlock('image')
      hero.style = { ...hero.style, width: 100, borderRadius: 12, paddingTop: 0, paddingBottom: 0 }
      const h = createBlock('heading')
      h.text = '🎤 Speaker Announced'
      h.style = { ...h.style, align: 'center', color: '#7c3aed', fontSize: 32 }
      const sub = createBlock('text')
      sub.html = 'We\'re thrilled to announce <strong>{{ speaker.name }}</strong> as our keynote speaker for <strong>{{ event.name }}</strong>.'
      sub.style = { ...sub.style, align: 'center', fontSize: 16 }
      const cta = createBlock('button')
      cta.text = 'See full lineup →'
      cta.style = { ...cta.style, align: 'center', backgroundColor: '#7c3aed', fullWidth: false }
      const footer = createBlock('text')
      footer.html = '© {{ system.year }} {{ organization.name }} · <a href="{{ unsubscribe_url }}">Unsubscribe</a>'
      footer.style = { align: 'center', fontSize: 12, color: '#94a3b8', lineHeight: '1.6', paddingTop: 8, paddingBottom: 18 }
      return [logo, hero, h, sub, cta, createBlock('divider'), footer]
    },
    settings: () => ({ ...defaultSettings(), backgroundColor: '#f5f3ff', linkColor: '#7c3aed' }),
  },
]
