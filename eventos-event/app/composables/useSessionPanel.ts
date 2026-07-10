/**
 * Live-session engagement panel data (Chat / Q&A / Polls / Attendees) for the
 * watch page. Backed by the participant-scoped endpoints under
 * /events/{event}/sessions/{session}/*. Realtime is simple client polling of
 * the active tab (no websockets) — cheap and robust at any scale.
 */
interface ChatMessage {
  id: number
  body: string
  author: string
  author_image: string | null
  is_mine: boolean
  upvotes: number
  voted: boolean
  is_answered: boolean
  created_at: string | null
}
interface PollOption { id: string, text: string, votes: number }
interface Poll {
  id: number
  question: string
  options: PollOption[]
  total_votes: number
  is_active: boolean
  my_vote: string | null
}
interface Attendee {
  id: string
  name: string
  image_url: string | null
  headline: string | null
  is_speaker: boolean
  online: boolean
}

export function useSessionPanel() {
  const api = useApi()

  const chat = ref<ChatMessage[]>([])
  const questions = ref<ChatMessage[]>([])
  const polls = ref<Poll[]>([])
  const attendees = ref<Attendee[]>([])
  const attendeeMeta = ref<{ online: number, total: number }>({ online: 0, total: 0 })

  let eventUuid = ''
  let sessionId = ''
  const bind = (ev: string, sid: string) => { eventUuid = ev; sessionId = sid }
  const base = () => `/events/${eventUuid}/sessions/${sessionId}`
  const ready = () => !!eventUuid && !!sessionId

  async function loadChat() {
    if (ready()) chat.value = (await api<any>(`${base()}/chat`)).data
  }
  async function sendChat(body: string) {
    if (!ready() || !body.trim()) return
    const { data } = await api<any>(`${base()}/chat`, { method: 'POST', body: { body: body.trim() } })
    chat.value.push(data)
  }
  async function loadQuestions() {
    if (ready()) questions.value = (await api<any>(`${base()}/questions`)).data
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
  async function loadPolls() {
    if (ready()) polls.value = (await api<any>(`${base()}/polls`)).data
  }
  async function votePoll(pollId: number, optionId: string) {
    if (!ready()) return
    polls.value = (await api<any>(`${base()}/polls/${pollId}/vote`, { method: 'POST', body: { option_id: optionId } })).data
  }
  async function loadAttendees() {
    if (!ready()) return
    const r = await api<any>(`${base()}/attendees`)
    attendees.value = r.data
    attendeeMeta.value = r.meta
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
    bind, loaderFor,
    sendChat, askQuestion, upvoteQuestion, votePoll,
  }
}
