/** A person tagged via @ in a feed post or comment. */
export interface FeedMention {
  id: string
  name: string
  avatar_url?: string | null
}

export interface MentionSegment {
  type: 'text' | 'mention'
  text: string
  mention?: FeedMention
}

/**
 * Detect an active @query at the caret — Facebook-style: `@` plus letters /
 * spaces / hyphens / apostrophes until the caret. Returns null when the caret
 * is not inside a mention draft.
 */
export function activeMentionQuery(text: string, caret: number): { start: number, query: string } | null {
  const before = text.slice(0, Math.max(0, Math.min(caret, text.length)))
  // Allow letters/digits/spaces/hyphens/apostrophes so "@Jane D" still searches.
  const m = before.match(/(^|[\s([{])@([A-Za-z0-9.'\- ]*)$/)
  if (!m) return null
  const query = (m[2] ?? '').replace(/\s+$/, '')
  // Two trailing spaces → abandon the draft mention.
  if (/\s{2}$/.test(m[2] ?? '')) return null
  const start = before.length - (m[2]?.length ?? 0) - 1 // index of '@'
  return { start, query }
}

/** Mentions whose `@Name` still appears in the body (prune deleted tags). */
export function pruneMentions(body: string, mentions: FeedMention[]): FeedMention[] {
  const seen = new Set<string>()
  const kept: FeedMention[] = []
  for (const m of mentions) {
    if (!m?.id || !m?.name || seen.has(m.id)) continue
    if (body.includes(`@${m.name}`)) {
      seen.add(m.id)
      kept.push({ id: m.id, name: m.name, avatar_url: m.avatar_url ?? null })
    }
  }
  return kept
}

/**
 * Split body into plain text + mention segments for rendering. Longer names
 * win first so "Jane Doe" isn't partially matched as "Jane".
 */
export function segmentMentions(body: string, mentions: FeedMention[] = []): MentionSegment[] {
  if (!body) return []
  if (!mentions.length) return [{ type: 'text', text: body }]

  const byName = [...mentions]
    .filter(m => m?.id && m?.name)
    .sort((a, b) => b.name.length - a.name.length)

  const markers: { start: number, end: number, mention: FeedMention }[] = []
  const used = new Set<number>()

  for (const mention of byName) {
    const needle = `@${mention.name}`
    let from = 0
    while (from < body.length) {
      const idx = body.indexOf(needle, from)
      if (idx < 0) break
      const end = idx + needle.length
      const overlaps = [...used].some(i => {
        const m = markers[i]!
        return idx < m.end && end > m.start
      })
      // Prefer word-ish boundaries so "@Ann" doesn't steal inside "@Anna".
      const after = body[end]
      const boundaryOk = after === undefined || /[\s.,!?;:)\]]/.test(after)
      if (!overlaps && boundaryOk) {
        used.add(markers.length)
        markers.push({ start: idx, end, mention })
      }
      from = idx + 1
    }
  }

  markers.sort((a, b) => a.start - b.start)

  const segments: MentionSegment[] = []
  let cursor = 0
  for (const m of markers) {
    if (m.start > cursor) {
      segments.push({ type: 'text', text: body.slice(cursor, m.start) })
    }
    segments.push({ type: 'mention', text: body.slice(m.start, m.end), mention: m.mention })
    cursor = m.end
  }
  if (cursor < body.length) {
    segments.push({ type: 'text', text: body.slice(cursor) })
  }
  return segments.length ? segments : [{ type: 'text', text: body }]
}
