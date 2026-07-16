import { defineStore } from 'pinia'
import type { JoinConfig } from '~/stores/rooms'

export interface MeetingPerson {
  name: string
  company: string
  job_title: string
  avatar_url: string | null
}

export interface MeetingParticipant {
  name: string
  role: 'host' | 'guest'
  rsvp: 'accepted' | 'declined' | 'pending'
}

export interface Meeting {
  id: string
  title: string | null
  agenda: string | null
  // Where the two of you meet, on a venue/hybrid event ("Hall 4"). Null online.
  location: string | null
  type: 'one_on_one' | 'group'
  status: 'requested' | 'confirmed' | 'declined' | 'canceled' | 'completed'
  direction: 'incoming' | 'outgoing'
  my_rsvp: 'accepted' | 'declined' | 'pending'
  can_respond: boolean
  starts_at: string | null
  ends_at: string | null
  date: string | null   // lounge slot day, YYYY-MM-DD
  slot: string | null   // lounge slot, HH:MM-HH:MM
  counterpart: MeetingPerson | null
  participants: MeetingParticipant[]
  // A booth meeting (attendee ↔ exhibitor) rather than a delegate one. Answered
  // by the exhibitor team in their own panel, so can_respond is always false.
  source: 'delegate' | 'exhibitor'
  exhibitor: string | null
  created_at: string | null
}

export interface MeetingRequest {
  to: string           // counterpart participation uuid
  title?: string
  agenda?: string
  location?: string    // required on a venue/hybrid event

  starts_at?: string
  ends_at?: string
  date?: string        // lounge slot day, YYYY-MM-DD
  slot?: string        // lounge slot, HH:MM-HH:MM
}

/**
 * The one-to-one meetings tab. Authenticated + scoped to the event via
 * useApi() → `/events/{uuid}/meetings`. A request is sent to a single delegate
 * (store), the invitee approves/rejects it (respond). Filtering into
 * Pending/Approved/Rejected is client-side over the full list.
 */
export const useMeetingsStore = defineStore('meetings', {
  state: () => ({
    meetings: [] as Meeting[],
    loading: false,
    loaded: false,
    error: false,
    sending: false,
    lastError: '' as string,
    acting: {} as Record<string, boolean>,
    joining: {} as Record<string, boolean>,
    joinError: '' as string,
  }),

  getters: {
    pending: (s): Meeting[] => s.meetings.filter(m => m.status === 'requested'),
    approved: (s): Meeting[] => s.meetings.filter(m => m.status === 'confirmed'),
    rejected: (s): Meeting[] => s.meetings.filter(m => m.status === 'declined' || m.status === 'canceled'),
  },

  actions: {
    async fetchMeetings() {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) { this.error = true; return }

      this.loading = true
      this.error = false
      try {
        const api = useApi()
        const res = await api<{ data: Meeting[] }>(`/events/${uuid}/meetings`)
        this.meetings = res.data
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },

    /** Send a meeting request to one delegate. Returns true on success. */
    async request(req: MeetingRequest): Promise<boolean> {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return false

      this.sending = true
      this.lastError = ''
      try {
        const api = useApi()
        const res = await api<{ data: Meeting }>(`/events/${uuid}/meetings`, {
          method: 'POST',
          body: {
            invitees: [req.to],
            title: req.title || null,
            agenda: req.agenda || null,
            location: req.location || null,
            starts_at: req.starts_at || null,
            ends_at: req.ends_at || null,
            date: req.date || null,
            slot: req.slot || null,
            type: 'one_on_one',
          },
        })
        // New request lands at the top of the list (outgoing, pending).
        this.meetings.unshift(res.data)
        return true
      } catch (e: any) {
        // Surface a server-provided reason (e.g. slot already booked) when present.
        this.lastError = e?.data?.message || e?.response?._data?.message || ''
        return false
      } finally {
        this.sending = false
      }
    },

    /** Join the live video room for a confirmed, currently-running meeting. */
    async join(meeting: Meeting): Promise<(JoinConfig & { title: string }) | null> {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return null

      this.joining[meeting.id] = true
      this.joinError = ''
      try {
        const api = useApi()
        const res = await api<{ data: JoinConfig & { title: string } }>(
          `/events/${uuid}/meetings/${meeting.id}/join`,
          { method: 'POST' },
        )
        return res.data
      } catch (e: any) {
        this.joinError = e?.data?.message || e?.response?._data?.message || 'Could not join the meeting.'
        return null
      } finally {
        this.joining[meeting.id] = false
      }
    },

    /** Approve / reject an incoming request, or cancel an outgoing one. */
    async respond(meeting: Meeting, action: 'accept' | 'reject' | 'cancel') {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return
      this.acting[meeting.id] = true
      try {
        const api = useApi()
        const res = await api<{ data: Meeting }>(`/events/${uuid}/meetings/${meeting.id}`, {
          method: 'PATCH',
          body: { action },
        })
        const i = this.meetings.findIndex(m => m.id === meeting.id)
        if (i !== -1) this.meetings[i] = res.data
      } finally {
        this.acting[meeting.id] = false
      }
    },
  },
})
