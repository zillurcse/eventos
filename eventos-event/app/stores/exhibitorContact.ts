import { defineStore } from 'pinia'

export interface ExhibitorMessage {
  id: string
  body: string
  side: 'attendee' | 'exhibitor'
  mine: boolean
  read_at: string | null
  created_at: string | null
}

export interface ExhibitorMeetingRequest {
  id: string
  status: 'requested' | 'assigned' | 'confirmed' | 'declined' | 'canceled'
  subject: string | null
  agenda: string | null
  starts_at: string | null
  ends_at: string | null
  date: string | null
  slot: string | null
  assigned_to: string | null
  created_at: string | null
}

export interface LoungeInfo {
  enabled: boolean
  timezone: string
  dates: string[]
  slots: Record<string, string[]>
  busy: Array<{ date: string, slot: string }>
}

interface ContactTarget { id: string, name: string }

/**
 * Attendee → exhibitor "Contact" modal (Chat + Meet). REST via useApi() against
 * `/events/{uuid}/exhibitors/{exhibitor}/*`. A message opens/continues a thread
 * with the booth; a meeting request is queued for the exhibitor admin to assign
 * a member to. One modal at a time, keyed by the target exhibitor.
 */
export const useExhibitorContactStore = defineStore('exhibitorContact', {
  state: () => ({
    open: false,
    tab: 'chat' as 'chat' | 'meet',
    target: null as ContactTarget | null,
    me: null as string | null, // my participation uuid (Echo channel key)
    channel: null as string | null, // currently-subscribed channel name

    // Chat
    messages: [] as ExhibitorMessage[],
    threadLoading: false,
    sending: false,

    // Meet
    lounge: null as LoungeInfo | null,
    loungeLoading: false,
    requests: [] as ExhibitorMeetingRequest[],
    requesting: false,

    error: '' as string,
  }),

  actions: {
    eventUuid(): string | null {
      return useSiteStore().event?.uuid ?? null
    },

    openFor(exhibitor: ContactTarget, tab: 'chat' | 'meet' = 'chat') {
      this.target = { id: exhibitor.id, name: exhibitor.name }
      this.tab = tab
      this.open = true
      this.error = ''
      this.messages = []
      this.requests = []
      this.loadThread()
      this.loadRequests()
      this.loadLounge()
    },

    close() {
      this.unsubscribe()
      this.open = false
      this.target = null
    },

    async loadThread() {
      const uuid = this.eventUuid()
      if (!uuid || !this.target) return
      this.threadLoading = true
      try {
        const api = useApi()
        const res = await api<{ data: { me: string | null, messages: ExhibitorMessage[] } }>(
          `/events/${uuid}/exhibitors/${this.target.id}/thread`,
        )
        this.messages = res.data.messages
        this.me = res.data.me
        this.subscribe()
      } catch { /* thread may not exist yet */ } finally {
        this.threadLoading = false
      }
    },

    /** Live delivery of exhibitor replies on my personal channel. */
    subscribe() {
      const uuid = this.eventUuid()
      if (!uuid || !this.me) return
      const { $echo } = useNuxtApp() as any
      if (!$echo) return

      const name = `event.${uuid}.exhibitor-contact.${this.me}`
      if (this.channel === name) return
      this.unsubscribe()

      $echo.channel(name).listen('.exhibitor.contact.message', (payload: any) => {
        // Only append to the thread that's currently open.
        if (!this.open || payload?.exhibitor_id !== this.target?.id) return
        if (this.messages.some(m => m.id === payload.message.id)) return
        this.messages.push({
          id: payload.message.id,
          body: payload.message.body,
          side: payload.message.side ?? 'exhibitor',
          mine: false,
          read_at: null,
          created_at: payload.message.created_at,
        })
      })
      this.channel = name
    },

    unsubscribe() {
      const uuid = this.eventUuid()
      if (!this.channel || !uuid) { this.channel = null; return }
      const { $echo } = useNuxtApp() as any
      $echo?.leaveChannel?.(this.channel)
      this.channel = null
    },

    async sendMessage(body: string): Promise<boolean> {
      const uuid = this.eventUuid()
      const text = body.trim()
      if (!uuid || !this.target || !text || this.sending) return false
      this.sending = true
      this.error = ''
      try {
        const api = useApi()
        const res = await api<{ data: { message: ExhibitorMessage } }>(
          `/events/${uuid}/exhibitors/${this.target.id}/messages`,
          { method: 'POST', body: { body: text } },
        )
        this.messages.push(res.data.message)
        // Surface this thread in the "Chats" list right away.
        const chat = useChatStore()
        if (chat.loaded) chat.syncExhibitorConversations()
        return true
      } catch (e: any) {
        this.error = e?.data?.message || 'Could not send your message.'
        return false
      } finally {
        this.sending = false
      }
    },

    async loadLounge() {
      const uuid = this.eventUuid()
      if (!uuid) return
      this.loungeLoading = true
      try {
        const api = useApi()
        const res = await api<{ data: LoungeInfo }>(`/events/${uuid}/lounge`)
        this.lounge = res.data
      } catch { /* lounge optional */ } finally {
        this.loungeLoading = false
      }
    },

    async loadRequests() {
      const uuid = this.eventUuid()
      if (!uuid || !this.target) return
      try {
        const api = useApi()
        const res = await api<{ data: ExhibitorMeetingRequest[] }>(
          `/events/${uuid}/exhibitors/${this.target.id}/meeting-requests`,
        )
        this.requests = res.data
      } catch { /* */ }
    },

    async requestMeeting(payload: { subject?: string, agenda?: string, date?: string, slot?: string }): Promise<boolean> {
      const uuid = this.eventUuid()
      if (!uuid || !this.target || this.requesting) return false
      this.requesting = true
      this.error = ''
      try {
        const api = useApi()
        const res = await api<{ data: ExhibitorMeetingRequest }>(
          `/events/${uuid}/exhibitors/${this.target.id}/meeting-requests`,
          { method: 'POST', body: {
            subject: payload.subject || null,
            agenda: payload.agenda || null,
            date: payload.date || null,
            slot: payload.slot || null,
          } },
        )
        this.requests.unshift(res.data)
        return true
      } catch (e: any) {
        this.error = e?.data?.message || 'Could not send your meeting request.'
        return false
      } finally {
        this.requesting = false
      }
    },
  },
})
