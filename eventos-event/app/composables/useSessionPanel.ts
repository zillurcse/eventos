/**
 * Live-session engagement panel data (Chat / Q&A / Polls / Attendees) for the
 * watch page. Backed by the participant-scoped endpoints under
 * /events/{event}/sessions/{session}/*. Realtime is simple client polling of
 * the active tab (no websockets) — cheap and robust at any scale.
 *
 * Hosts (session speakers + event staff) get moderation on the same endpoints:
 * hide/pin/delete a message, approve or reject a pending question, run the poll
 * lifecycle, and mute a participant. `canModerate` comes from the server on
 * every response — it decides what we render, never what we're allowed to do.
 */
export type PanelRole = 'organizer' | 'speaker' | 'attendee'
export interface PanelMessage {
  id: number
  body: string
  author: string
  author_image: string | null
  author_id: string | null
  author_role: PanelRole
  /** From the stage or the organizers — carries the badge, closes the question. */
  is_official: boolean
  is_mine: boolean
  upvotes: number
  voted: boolean
  is_answered: boolean
  is_hidden: boolean
  is_pinned: boolean
  status: 'published' | 'pending' | 'rejected'
  can_delete: boolean
  created_at: string | null
  /** Answers, oldest first. Only questions carry these. */
  replies: PanelMessage[]
}
export interface PollOption { id: string, text: string, votes: number }
export interface Poll {
  id: number
  question: string
  options: PollOption[]
  total_votes: number
  status: 'draft' | 'live' | 'closed'
  is_active: boolean
  show_results: boolean
  results_visible: boolean
  my_vote: string | null
}
export interface PanelAttendee {
  id: string
  name: string
  image_url: string | null
  headline: string | null
  is_speaker: boolean
  is_muted: boolean
  online: boolean
}
interface PanelMeta {
  can_moderate: boolean
  is_muted: boolean
  qa_moderation: boolean
  /** Who the organizer lets reply to questions. */
  qa_answer_policy: 'organizers' | 'hosts' | 'everyone'
  can_answer: boolean
  my_role: PanelRole
  pending_count: number
}

export function useSessionPanel() {
  const api = useApi()

  const chat = ref<PanelMessage[]>([])
  const questions = ref<PanelMessage[]>([])
  const polls = ref<Poll[]>([])
  const attendees = ref<PanelAttendee[]>([])
  const attendeeMeta = ref<{ online: number, total: number }>({ online: 0, total: 0 })

  const canModerate = ref(false)
  const isMuted = ref(false)
  const qaModeration = ref(false)
  const qaAnswerPolicy = ref<PanelMeta['qa_answer_policy']>('hosts')
  const canAnswer = ref(false)
  const myRole = ref<PanelRole>('attendee')
  const pendingCount = ref(0)

  let eventUuid = ''
  let sessionId = ''
  const bind = (ev: string, sid: string) => { eventUuid = ev; sessionId = sid }
  const base = () => `/events/${eventUuid}/sessions/${sessionId}`
  const ready = () => !!eventUuid && !!sessionId

  /** Every panel response carries the caller's capabilities — keep them fresh. */
  function absorb(meta?: Partial<PanelMeta>) {
    if (!meta) return
    canModerate.value = !!meta.can_moderate
    isMuted.value = !!meta.is_muted
    qaModeration.value = !!meta.qa_moderation
    qaAnswerPolicy.value = meta.qa_answer_policy ?? 'hosts'
    canAnswer.value = !!meta.can_answer
    myRole.value = meta.my_role ?? 'attendee'
    pendingCount.value = meta.pending_count ?? 0
  }

  // ── Chat ──────────────────────────────────────────────────────────────────
  async function loadChat() {
    if (!ready()) return
    const r = await api<any>(`${base()}/chat`)
    chat.value = r.data
    absorb(r.meta)
  }
  async function sendChat(body: string) {
    if (!ready() || !body.trim()) return
    const { data } = await api<any>(`${base()}/chat`, { method: 'POST', body: { body: body.trim() } })
    chat.value.push(data)
  }

  // ── Q&A ───────────────────────────────────────────────────────────────────
  async function loadQuestions() {
    if (!ready()) return
    const r = await api<any>(`${base()}/questions`)
    questions.value = r.data
    absorb(r.meta)
  }
  async function askQuestion(body: string) {
    if (!ready() || !body.trim()) return
    await api(`${base()}/questions`, { method: 'POST', body: { body: body.trim() } })
    await loadQuestions()
  }
  async function upvoteQuestion(id: number) {
    if (!ready()) return
    await api(`${base()}/questions/${id}/upvote`, { method: 'POST' })
    await loadQuestions()
  }
  /** Reply to a question. The server re-checks the session's answer policy. */
  async function replyToQuestion(id: number, body: string) {
    if (!ready() || !body.trim()) return
    await api(`${base()}/questions/${id}/replies`, { method: 'POST', body: { body: body.trim() } })
    await loadQuestions()
  }

  // ── Moderation (host) ─────────────────────────────────────────────────────
  /** Patch one message, then refresh whichever list it belongs to. */
  async function moderate(m: PanelMessage, patch: Record<string, unknown>, kind: 'chat' | 'qa') {
    if (!ready()) return
    await api(`${base()}/messages/${m.id}`, { method: 'PATCH', body: patch })
    await (kind === 'chat' ? loadChat() : loadQuestions())
  }
  async function removeMessage(m: PanelMessage, kind: 'chat' | 'qa') {
    if (!ready()) return
    await api(`${base()}/messages/${m.id}`, { method: 'DELETE' })
    await (kind === 'chat' ? loadChat() : loadQuestions())
  }

  // ── Polls ─────────────────────────────────────────────────────────────────
  async function loadPolls() {
    if (!ready()) return
    const r = await api<any>(`${base()}/polls`)
    polls.value = r.data
    absorb(r.meta)
  }
  async function votePoll(pollId: number, optionId: string) {
    if (!ready()) return
    const r = await api<any>(`${base()}/polls/${pollId}/vote`, { method: 'POST', body: { option_id: optionId } })
    polls.value = r.data
    absorb(r.meta)
  }
  // The poll writers all return the refreshed list, so one round trip does it.
  async function createPoll(body: { question: string, options: string[], status?: string, show_results?: boolean }) {
    if (!ready()) return
    const r = await api<any>(`${base()}/polls`, { method: 'POST', body })
    polls.value = r.data
    absorb(r.meta)
  }
  async function updatePoll(pollId: number, patch: Record<string, unknown>) {
    if (!ready()) return
    const r = await api<any>(`${base()}/polls/${pollId}`, { method: 'PATCH', body: patch })
    polls.value = r.data
    absorb(r.meta)
  }
  async function deletePoll(pollId: number) {
    if (!ready()) return
    const r = await api<any>(`${base()}/polls/${pollId}`, { method: 'DELETE' })
    polls.value = r.data
    absorb(r.meta)
  }

  // ── Attendees + mutes ─────────────────────────────────────────────────────
  async function loadAttendees() {
    if (!ready()) return
    const r = await api<any>(`${base()}/attendees`)
    attendees.value = r.data
    attendeeMeta.value = { online: r.meta.online, total: r.meta.total }
    absorb(r.meta)
  }
  async function toggleMute(a: PanelAttendee) {
    if (!ready()) return
    if (a.is_muted) await api(`${base()}/mutes/${a.id}`, { method: 'DELETE' })
    else await api(`${base()}/mutes`, { method: 'POST', body: { participation: a.id } })
    await loadAttendees()
  }

  /** Load the given tab now and return its refresh fn for polling. */
  function loaderFor(tab: string): (() => Promise<void>) | null {
    switch (tab) {
      case 'chat': return loadChat
      case 'qa': return loadQuestions
      case 'polls': return loadPolls
      case 'attendees': return loadAttendees
      default: return null
    }
  }

  return {
    chat, questions, polls, attendees, attendeeMeta,
    canModerate, isMuted, qaModeration, qaAnswerPolicy, canAnswer, myRole, pendingCount,
    bind, loaderFor,
    sendChat, askQuestion, upvoteQuestion, replyToQuestion, votePoll,
    moderate, removeMessage,
    createPoll, updatePoll, deletePoll,
    toggleMute,
  }
}
