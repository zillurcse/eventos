/**
 * Reading a spreadsheet in the browser, with no dependency.
 *
 * Organizers keep their dropdown lists in Excel, so "save as CSV first" is a
 * step they routinely get wrong (wrong delimiter, wrong sheet). CSV/TSV is a
 * hand-rolled RFC-4180 reader; .xlsx is a zip we inflate with the platform's
 * own DecompressionStream and read just enough of to recover the cell grid.
 * Everything stays client-side, so the preview is instant.
 */

/** RFC-4180 reader: quoted fields, embedded separators, doubled quotes, both line endings. */
export function parseDelimited(text: string, sep = ','): string[][] {
  const rows: string[][] = []
  let row: string[] = []
  let field = ''
  let quoted = false

  for (let i = 0; i < text.length; i++) {
    const c = text[i]

    if (quoted) {
      if (c === '"') {
        if (text[i + 1] === '"') { field += '"'; i++ }
        else quoted = false
      } else field += c
      continue
    }

    if (c === '"') quoted = true
    else if (c === sep) { row.push(field); field = '' }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && text[i + 1] === '\n') i++
      row.push(field); field = ''
      if (row.some(v => v.trim() !== '')) rows.push(row)
      row = []
    } else field += c
  }

  row.push(field)
  if (row.some(v => v.trim() !== '')) rows.push(row)

  return rows
}

/** Comma or tab, whichever wins on the first line — Excel's clipboard/exports use both. */
export function parseText(text: string): string[][] {
  const first = text.split(/\r?\n/, 1)[0] || ''
  const commas = (first.match(/,/g) || []).length
  const tabs = (first.match(/\t/g) || []).length
  return parseDelimited(text, tabs > commas ? '\t' : ',')
}

// ── xlsx ───────────────────────────────────────────────────────────

const XLSX_UNSUPPORTED = 'This browser cannot read .xlsx files — please save the sheet as CSV and upload that.'

function unzip(buf: ArrayBuffer): Map<string, { method: number, start: number, size: number }> {
  const view = new DataView(buf)
  const bytes = new Uint8Array(buf)
  const entries = new Map<string, { method: number, start: number, size: number }>()

  // End-of-central-directory: fixed 22 bytes plus a comment of up to 64 KB.
  let eocd = -1
  for (let i = buf.byteLength - 22; i >= 0 && i >= buf.byteLength - 22 - 0xffff; i--) {
    if (view.getUint32(i, true) === 0x06054b50) { eocd = i; break }
  }
  if (eocd < 0) throw new Error('not a zip')

  const count = view.getUint16(eocd + 10, true)
  let p = view.getUint32(eocd + 16, true)

  for (let n = 0; n < count; n++) {
    if (view.getUint32(p, true) !== 0x02014b50) break
    const method = view.getUint16(p + 10, true)
    const size = view.getUint32(p + 20, true)
    const nameLen = view.getUint16(p + 28, true)
    const extraLen = view.getUint16(p + 30, true)
    const commentLen = view.getUint16(p + 32, true)
    const local = view.getUint32(p + 42, true)
    const name = new TextDecoder().decode(bytes.subarray(p + 46, p + 46 + nameLen))

    // The local header repeats the name and carries its own extra field, so the
    // data offset can only be computed from the local header itself.
    const start = local + 30 + view.getUint16(local + 26, true) + view.getUint16(local + 28, true)
    entries.set(name, { method, start, size })

    p += 46 + nameLen + extraLen + commentLen
  }

  return entries
}

async function inflate(bytes: Uint8Array, method: number): Promise<string> {
  if (method === 0) return new TextDecoder().decode(bytes)
  if (method !== 8) throw new Error('unsupported compression')
  if (typeof DecompressionStream === 'undefined') throw new Error(XLSX_UNSUPPORTED)

  const stream = new Blob([bytes]).stream().pipeThrough(new DecompressionStream('deflate-raw'))
  return new Response(stream).text()
}

const ENTITIES: Record<string, string> = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'" }
const decodeXml = (s: string) => s.replace(/&(#x?[0-9a-f]+|\w+);/gi, (m, e: string) =>
  e[0] === '#'
    ? String.fromCodePoint(parseInt(e[1] === 'x' || e[1] === 'X' ? e.slice(2) : e.slice(1), e[1] === 'x' || e[1] === 'X' ? 16 : 10))
    : ENTITIES[e.toLowerCase()] ?? m)

/** Every <t> inside the element, joined — rich text splits one string across runs. */
const textOf = (xml: string) =>
  [...xml.matchAll(/<t[^>]*>([\s\S]*?)<\/t>/g)].map(m => decodeXml(m[1]!)).join('')

/** Column letters → 0-based index: A→0, Z→25, AA→26. */
function colIndex(ref: string): number {
  const letters = ref.match(/^[A-Z]+/)?.[0] || 'A'
  let n = 0
  for (const ch of letters) n = n * 26 + (ch.charCodeAt(0) - 64)
  return n - 1
}

/** First worksheet of an .xlsx workbook as a row × column grid of strings. */
export async function parseXlsx(file: File): Promise<string[][]> {
  const buf = await file.arrayBuffer()
  const bytes = new Uint8Array(buf)
  const entries = unzip(buf)

  const read = async (name: string) => {
    const e = entries.get(name)
    if (!e) return ''
    return inflate(bytes.subarray(e.start, e.start + e.size), e.method)
  }

  const sheetName = entries.has('xl/worksheets/sheet1.xml')
    ? 'xl/worksheets/sheet1.xml'
    : [...entries.keys()].filter(k => /^xl\/worksheets\/.+\.xml$/.test(k)).sort()[0]
  if (!sheetName) throw new Error('That workbook has no readable sheet.')

  const [sheet, sharedXml] = await Promise.all([read(sheetName), read('xl/sharedStrings.xml')])
  const shared = [...sharedXml.matchAll(/<si>([\s\S]*?)<\/si>/g)].map(m => textOf(m[1]!))

  const rows: string[][] = []
  for (const rowMatch of sheet.matchAll(/<row[^>]*>([\s\S]*?)<\/row>/g)) {
    const cells: string[] = []
    for (const cell of rowMatch[1]!.matchAll(/<c([^>]*)\/>|<c([^>]*)>([\s\S]*?)<\/c>/g)) {
      const attrs = cell[1] ?? cell[2] ?? ''
      const body = cell[3] ?? ''
      const i = colIndex(/r="([A-Z]+)/.exec(attrs)?.[1] || '')
      const type = /t="(\w+)"/.exec(attrs)?.[1]

      let value = ''
      if (type === 's') value = shared[Number(/<v>([\s\S]*?)<\/v>/.exec(body)?.[1] ?? -1)] ?? ''
      else if (type === 'inlineStr') value = textOf(body)
      else value = decodeXml(/<v>([\s\S]*?)<\/v>/.exec(body)?.[1] ?? '')

      while (cells.length < i) cells.push('')
      cells[i] = value
    }
    if (cells.some(v => v.trim() !== '')) rows.push(cells)
  }

  return rows
}

/** Reads .csv / .tsv / .txt / .xlsx into a grid; rejects with a message worth showing. */
export async function parseSpreadsheet(file: File): Promise<string[][]> {
  if (/\.xlsx$/i.test(file.name)) {
    try {
      return await parseXlsx(file)
    } catch (e: any) {
      throw new Error(e?.message === XLSX_UNSUPPORTED ? XLSX_UNSUPPORTED : 'That .xlsx file could not be read — try re-saving it, or upload a CSV.')
    }
  }
  if (/\.xls$/i.test(file.name)) {
    throw new Error('The old .xls format is not supported — save the sheet as .xlsx or CSV.')
  }
  return parseText(await file.text())
}
