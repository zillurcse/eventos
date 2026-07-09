import { defineStore } from 'pinia'

export interface ChatPerson {
  id: string
  name: string
  role: 'attendee' | 'speaker' | 'exhibitor' | 'sponsor'
  company: string
  job_title: string
  avatar_url: string | null
}

export interface ChatPreview {
  body: string
  mine: boolean
  created_at: string | null
}

export interface ChatConversationItem {
  id: string
  with: ChatPerson
  unread: number
  last_message: ChatPreview | null
  // 'person' = participant chat thread; 'exhibitor' = a booth "Contact" thread
  // (opens the exhibitor Contact modal instead of the in-drawer thread).
  kind?: 'person' | 'exhibitor'
  exhibitor_id?: string
}

export type ChatAttachmentKind = 'image' | 'video' | 'pdf' | 'doc' | 'excel' | 'file'

export interface ChatAttachment {
  kind: ChatAttachmentKind
  url: string
  name?: string | null
}

export interface ChatMessageItem {
  id: string
  body: string
  attachments: ChatAttachment[]
  mine: boolean
  read_at: string | null
  created_at: string | null
}

interface LiveChatPayload {
  conversation_id: string
  message: {
    id: string
    body: string
    attachments?: ChatAttachment[]
    preview?: string
    sender: string
    created_at: string | null
  }
}

/** Map an uploaded file's mime/extension onto a chat attachment kind. */
function kindOf(mime: string | null, filename: string | null): ChatAttachmentKind {
  const m = mime || ''
  if (m.startsWith('image/')) return 'image'
  if (m.startsWith('video/')) return 'video'
  if (m.includes('pdf')) return 'pdf'
  const ext = (filename || '').split('.').pop()?.toLowerCase() || ''
  if (['doc', 'docx', 'txt', 'ppt', 'pptx'].includes(ext) || m.includes('word') || m.includes('presentation')) return 'doc'
  if (['xls', 'xlsx', 'csv'].includes(ext) || m.includes('sheet') || m.includes('excel') || m.includes('csv')) return 'excel'
  return 'file'
}

/** Inbox preview line for an attachment-only message (mirrors the API). */
function previewForAttachment(a?: ChatAttachment): string {
  if (!a) return ''
  if (a.kind === 'image') return '📷 Photo'
  if (a.kind === 'video') return '🎬 Video'
  return `📎 ${a.name || 'File'}`
}

/**
 * One-to-one participant chat ("Chat" in the topbar). REST via useApi()
 * against `/events/{uuid}/chat*`; live delivery over Reverb on the personal
 * channel `event.{event uuid}.chat.{my participation uuid}` (both sides of a
 * thread receive `chat.message.created`, so other tabs stay in sync too).
 */
export const useChatStore = defineStore('chat', {
  state: () => ({
    me: null as string | null, // my participation uuid (Echo channel key)
    profile: null as ChatPerson | null, // my own display card (thread view)
    drawerOpen: false, // topbar slide-over (EXPOUSE-style Conversations panel)
    conversations: [] as ChatConversationItem[],
    loaded: false,
    loading: false,
    activeId: null as string | null,
    messages: [] as ChatMessageItem[],
    messagesLoading: false,
    sending: false,
    subscribed: false,
  }),

  getters: {
    unreadTotal: s => s.conversations.reduce((n, c) => n + c.unread, 0),
    active: s => s.conversations.find(c => c.id === s.activeId) ?? null,
  },

  actions: {
    eventUuid(): string | null {
      return useSiteStore().event?.uuid ?? null
    },

    toggleDrawer() {
      this.drawerOpen = !this.drawerOpen
      if (this.drawerOpen && !this.loaded) this.fetchInbox()
    },

    closeDrawer() {
      this.drawerOpen = false
    },

    async fetchInbox() {
      const uuid = this.eventUuid()
      if (!uuid) return
      this.loading = true
      try {
        const api = useApi()
        const res = await api<{ me: string, profile: ChatPerson | null, data: ChatConversationItem[] }>(`/events/${uuid}/chat`)
        this.me = res.me
        this.profile = res.profile
        this.conversations = res.data.map(c => ({ ...c, kind: 'person' as const }))
        this.loaded = true
        this.subscribe()
        await this.syncExhibitorConversations()
      } finally {
        this.loading = false
      }
    },

    /**
     * Merge the attendee's exhibitor "Contact" threads into the conversation
     * list so they appear alongside participant chats. They open the Contact
     * modal rather than the in-drawer thread.
     */
    async syncExhibitorConversations() {
      const uuid = this.eventUuid()
      if (!uuid) return
      try {
        const api = useApi()
        const res = await api<{ data: Array<{ id: string, exhibitor_id: string, name: string, unread: number, last_message: ChatPreview | null }> }>(
          `/events/${uuid}/exhibitor-conversations`,
        )
        const items: ChatConversationItem[] = res.data.map(c => ({
          id: c.id,
          kind: 'exhibitor',
          exhibitor_id: c.exhibitor_id,
          with: { id: c.exhibitor_id, name: c.name, role: 'exhibitor', company: '', job_title: '', avatar_url: null },
          unread: c.unread,
          last_message: c.last_message,
        }))
        // Replace the exhibitor slice, keep person threads, then order by recency.
        const persons = this.conversations.filter(c => c.kind !== 'exhibitor')
        this.conversations = [...persons, ...items].sort((a, b) => {
          const ta = a.last_message?.created_at ? Date.parse(a.last_message.created_at) : 0
          const tb = b.last_message?.created_at ? Date.parse(b.last_message.created_at) : 0
          return tb - ta
        })
      } catch { /* exhibitor threads are optional */ }
    },

    /** Route a list row: participant threads open in-drawer; booths open the modal. */
    openConversation(c: ChatConversationItem) {
      if (c.kind === 'exhibitor' && c.exhibitor_id) {
        this.closeDrawer()
        useExhibitorContactStore().openFor({ id: c.exhibitor_id, name: c.with.name })
        return
      }
      this.select(c.id)
    },

    /** Select a thread: load its history and clear its unread badge. */
    async select(conversationId: string) {
      const uuid = this.eventUuid()
      if (!uuid) return
      this.activeId = conversationId
      this.messages = []
      this.messagesLoading = true
      try {
        const api = useApi()
        const res = await api<{ data: ChatMessageItem[] }>(`/events/${uuid}/chat/${conversationId}/messages`)
        // Ignore a stale response if the user already clicked another thread.
        if (this.activeId !== conversationId) return
        this.messages = res.data
        const c = this.conversations.find(x => x.id === conversationId)
        if (c) c.unread = 0
      } finally {
        this.messagesLoading = false
      }
    },

    /** Start (or resume) a thread with a person from the directory. */
    async openWith(participantId: string) {
      const uuid = this.eventUuid()
      if (!uuid) return
      const api = useApi()
      const res = await api<{ data: ChatConversationItem }>(`/events/${uuid}/chat`, {
        method: 'POST',
        body: { participant: participantId },
      })
      if (!this.conversations.some(c => c.id === res.data.id)) {
        this.conversations.unshift(res.data)
      }
      await this.select(res.data.id)
    },

    async send(body: string, attachments: ChatAttachment[] = []) {
      const uuid = this.eventUuid()
      const id = this.activeId
      if (!uuid || !id || (!body.trim() && !attachments.length) || this.sending) return
      this.sending = true
      try {
        const api = useApi()
        const res = await api<{ data: ChatMessageItem }>(`/events/${uuid}/chat/${id}/messages`, {
          method: 'POST',
          body: { body: body.trim(), attachments },
        })
        this.messages.push(res.data)
        this.touchPreview(id, {
          body: res.data.body || previewForAttachment(res.data.attachments[0]),
          mine: true,
          created_at: res.data.created_at,
        })
      } finally {
        this.sending = false
      }
    },

    /** Upload one chat attachment (image/video/pdf/doc/excel) → its URL. */
    async uploadMedia(file: File): Promise<{ url: string, filename: string | null, kind: ChatAttachmentKind }> {
      const uuid = this.eventUuid()
      if (!uuid) throw new Error('No event context')
      const api = useApi()
      const form = new FormData()
      form.append('file', file)
      form.append('collection', 'chat')
      const res = await api<{ data: { url: string, mime_type: string | null, filename: string | null } }>(
        `/events/${uuid}/uploads`,
        { method: 'POST', body: form },
      )
      return { url: res.data.url, filename: res.data.filename, kind: kindOf(res.data.mime_type, res.data.filename) }
    },

    /** Live delivery: one personal channel covers every thread I'm in. */
    subscribe() {
      const uuid = this.eventUuid()
      if (this.subscribed || !uuid || !this.me) return
      const { $echo } = useNuxtApp() as any
      if (!$echo) return

      $echo.channel(`event.${uuid}.chat.${this.me}`)
        .listen('.chat.message.created', (payload: LiveChatPayload) => this.onLiveMessage(payload))
      this.subscribed = true
    },

    async onLiveMessage(payload: LiveChatPayload) {
      const mine = payload.message.sender === this.me
      const convo = this.conversations.find(c => c.id === payload.conversation_id)

      // A thread we've never seen (someone just messaged us first): reload.
      if (!convo) {
        await this.fetchInbox()
        return
      }

      this.touchPreview(convo.id, {
        body: payload.message.preview || payload.message.body,
        mine,
        created_at: payload.message.created_at,
      })

      if (this.activeId === payload.conversation_id) {
        // Visible thread: append (dedupe echoes of my own optimistic push).
        if (!this.messages.some(m => m.id === payload.message.id)) {
          this.messages.push({
            id: payload.message.id,
            body: payload.message.body,
            attachments: payload.message.attachments ?? [],
            mine,
            read_at: null,
            created_at: payload.message.created_at,
          })
        }
        if (!mine) {
          const uuid = this.eventUuid()
          const api = useApi()
          api(`/events/${uuid}/chat/${convo.id}/read`, { method: 'PATCH' }).catch(() => {})
        }
      } else if (!mine) {
        convo.unread += 1
      }
    },

    /** Update a conversation's preview line and float it to the top. */
    touchPreview(conversationId: string, preview: ChatPreview) {
      const i = this.conversations.findIndex(c => c.id === conversationId)
      if (i < 0) return
      const [c] = this.conversations.splice(i, 1)
      c!.last_message = preview
      this.conversations.unshift(c!)
    },
  },
})
