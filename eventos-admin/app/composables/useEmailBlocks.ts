/**
 * Block model for the email builder. Blocks are flat objects { id, type, ...props,
 * style } that serialize 1:1 to what the Laravel EmailRenderer consumes. A
 * `columns` block nests child blocks per column. Kept framework-agnostic so the
 * editor, canvas and inspector all share one source of truth.
 */

export type BlockType =
  | 'heading' | 'text' | 'button' | 'image'
  | 'divider' | 'spacer' | 'social' | 'columns' | 'html'

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
      return { ...base, columns: [[createBlock('text')], [createBlock('text')]], style: { gap: 16, paddingTop: 8, paddingBottom: 8 } }
    case 'html':
      return { ...base, html: '<!-- Your custom HTML -->', style: { paddingTop: 8, paddingBottom: 8 } }
    default:
      return base
  }
}

export interface PaletteItem { type: BlockType, label: string, icon: string }

/** Left-rail palette. `icon` is an inline SVG path string (24x24, stroked). */
export const PALETTE: PaletteItem[] = [
  { type: 'heading', label: 'Heading', icon: 'M6 4v16M18 4v16M6 12h12' },
  { type: 'text', label: 'Text', icon: 'M4 6h16M4 12h16M4 18h11' },
  { type: 'button', label: 'Button', icon: 'M3 9h18v6H3z M7 12h10' },
  { type: 'image', label: 'Image', icon: 'M3 5h18v14H3z M8 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4z M21 17l-5-5-9 7' },
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
