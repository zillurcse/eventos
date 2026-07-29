import { defineStore } from 'pinia'
import { useApi } from '~/composables/useApi'
import { eventIdentityHeaders, useEventIdentity } from '~/composables/useEventSubdomain'
import type { JoinConfig } from '~/stores/rooms'
import { useSiteStore } from '~/stores/site'
import type { ReceptionAd } from '~/stores/reception'

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
  allocated_table: MeetingAllocatedTable | null
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

export interface MeetingAllocatedTable {
  id: string
  name: string
  capacity: number
  design: 'round' | 'boardroom' | 'lounge'
  image_url: string | null
  accent: string | null
}

export interface MeetingAreaTable extends MeetingAllocatedTable {
  bookings: Array<{ date: string, slot: string, status: string }>
}

export interface MeetingPartner {
  id: string
  name: string
  role: 'attendee' | 'speaker' | 'exhibitor' | 'sponsor'
  company: string
  job_title: string
  avatar_url: string | null
}

export interface MeetingCapabilities {
  enabled: boolean
  role: string
  allowed_roles: string[]
  restrictions: {
    requests: number | null
    confirmed: number | null
    requests_used: number
    confirmed_used: number
  }
  slot_duration: number
  intelligent: boolean
  locations: string[]
  can_request: boolean
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
    ads: [] as ReceptionAd[],
    adsLoaded: false,
    capabilities: null as MeetingCapabilities | null,
    capabilitiesLoaded: false,
    areaTables: [] as MeetingAreaTable[],
    areaLoaded: false,
  }),

  getters: {
    pending: (s): Meeting[] => s.meetings.filter(m => m.status === 'requested'),
    approved: (s): Meeting[] => s.meetings.filter(m => m.status === 'confirmed'),
    rejected: (s): Meeting[] => s.meetings.filter(m => m.status === 'declined' || m.status === 'canceled'),
    canRequest: (s): boolean => {
      const site = useSiteStore()
      if (!site.meetingsTabEnabled) return false
      if (s.capabilities?.enabled === false) return false
      const allowed = s.capabilities?.allowed_roles
      if (allowed && allowed.length === 0) return false
      return s.capabilities?.can_request !== false
    },
    allowedRoles: (s): string[] => s.capabilities?.allowed_roles ?? ['attendee', 'speaker', 'exhibitor', 'sponsor'],
    canMeetRole: (s) => (role: string): boolean => {
      const allowed = s.capabilities?.allowed_roles
      if (!allowed?.length) return true
      return allowed.includes(role)
    },
    intelligent: (s): boolean => s.capabilities?.intelligent === true,
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

    /** Role matrix + caps from Admin → Communication → Meetings. */
    async fetchCapabilities(options?: { force?: boolean }) {
      if (this.capabilitiesLoaded && !options?.force) return
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return
      this.capabilitiesLoaded = false
      try {
        const api = useApi()
        const res = await api<{ data: MeetingCapabilities }>(`/events/${uuid}/meetings/capabilities`, { silent: true })
        this.capabilities = res.data
      } catch {
        this.capabilities = null
      } finally {
        this.capabilitiesLoaded = true
      }
    },

    /** Attendee tables + bookings for the meeting area map (Intelligent Meeting). */
    async fetchArea() {
      if (this.areaLoaded || !this.capabilities?.intelligent) return
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return
      try {
        const api = useApi()
        const res = await api<{ data: { tables: MeetingAreaTable[] } }>(`/events/${uuid}/meetings/area`)
        this.areaTables = res.data.tables
      } catch {
        this.areaTables = []
      } finally {
        this.areaLoaded = true
      }
    },

    /** People this viewer may request a meeting with (permission matrix). */
    async fetchPartners(q?: string, role?: string): Promise<MeetingPartner[]> {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return []
      try {
        const api = useApi()
        const query: Record<string, string> = {}
        if (q?.trim()) query.q = q.trim()
        if (role) query.role = role
        const res = await api<{ data: MeetingPartner[], roles: string[] }>(
          `/events/${uuid}/meetings/partners`,
          { query },
        )
        if (this.capabilities) {
          this.capabilities = { ...this.capabilities, allowed_roles: res.roles }
        }
        return res.data
      } catch {
        return []
      }
    },

    /** The organizer's "main ads" strip targeted at the Meetings page. */
    async fetchAds() {
      if (this.adsLoaded) return
      const identity = useEventIdentity()
      const id = identity.subdomain || identity.host
      if (!id) return
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: { strip: ReceptionAd[], sidebar: ReceptionAd[] } }>(`${apiBase}/public/ads`, {
          query: { page: 'meetings' },
          headers: eventIdentityHeaders(),
        })
        this.ads = res.data.strip
      } catch {
        // Ads are decorative — the page works fine without them.
      } finally {
        this.adsLoaded = true
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
        if (this.capabilities) {
          this.capabilities.restrictions.requests_used += 1
          const max = this.capabilities.restrictions.requests
          if (max !== null && this.capabilities.restrictions.requests_used >= max) {
            this.capabilities.can_request = false
          }
        }
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
        if (action === 'accept' && this.capabilities) {
          this.capabilities.restrictions.confirmed_used += 1
          if (this.capabilities.intelligent) {
            this.areaLoaded = false
            this.fetchArea()
          }
        }
      } finally {
        this.acting[meeting.id] = false
      }
    },
  },
})
