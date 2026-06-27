<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface EventInfo {
  id: string
  name: string
  status: string
  published_at: string | null
  is_public: boolean
  starts_at: string | null
  ends_at: string | null
}

interface ChecklistItem { key: string, label: string, done: boolean, to: string | null }

const event = ref<EventInfo | null>(null)
const checklist = ref<ChecklistItem[]>([])
const completed = ref(0)
const total = ref(0)
const websiteUrl = ref('')
const busy = ref(false)
const copied = ref(false)

const NuxtLink = resolveComponent('NuxtLink')

const isPublished = computed(() => event.value?.status === 'published')
const readyCount = computed(() => completed.value)
const allReady = computed(() => total.value > 0 && completed.value >= total.value)

async function load() {
  try {
    const [ev, ov, settings] = await Promise.all([
      api<{ data: EventInfo }>(`/events/${id}`),
      api<any>(`/events/${id}/overview`),
      api<any>(`/events/${id}/settings`),
    ])
    event.value = ev.data
    checklist.value = ov.data.checklist || []
    completed.value = ov.data.completed || 0
    total.value = ov.data.total || 0
    const sub = settings.data.domain?.subdomain
    const custom = settings.data.domain?.custom_domain
    websiteUrl.value = custom ? `https://${custom}` : `https://${sub || 'your-event'}.eventos.app`
  } catch { /* */ }
}

async function setStatus(status: 'draft' | 'published') {
  if (status === 'draft' && !confirm('Unpublish this event? It will no longer be visible to attendees.')) return
  busy.value = true
  try {
    const res = await api<{ data: EventInfo }>(`/events/${id}/publish`, { method: 'POST', body: { status } })
    event.value = res.data
  } catch { /* */ } finally {
    busy.value = false
  }
}

async function setVisibility(isPublic: boolean) {
  busy.value = true
  try {
    const res = await api<{ data: EventInfo }>(`/events/${id}/publish`, { method: 'POST', body: { status: event.value?.status, is_public: isPublic } })
    event.value = res.data
  } catch { /* */ } finally {
    busy.value = false
  }
}

async function copyUrl() {
  try {
    await navigator.clipboard.writeText(websiteUrl.value)
    copied.value = true; setTimeout(() => (copied.value = false), 1500)
  } catch { /* */ }
}

function sectionLink(to: string | null): string | null {
  if (!to) return null
  if (to === 'team') return '/org/team'
  return `/org/events/${id}/${to}`
}

function fmtDateTime(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(load)
</script>

<template>
  <div v-if="event" class="max-w-[860px]">
    <div class="mb-4">
      <h2 class="section-title m-0">Publishing</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Control whether your event website is live and visible to attendees.</p>
    </div>

    <!-- Status hero -->
    <div
      class="card mb-4 border-l-4"
      :class="isPublished ? 'border-l-green-500' : 'border-l-amber-400'"
    >
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <div class="flex items-center gap-2.5 mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[.8rem] font-semibold"
              :class="isPublished ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
            >
              <span class="w-2 h-2 rounded-full" :class="isPublished ? 'bg-green-500' : 'bg-amber-400'" />
              {{ isPublished ? 'Published' : 'Draft' }}
            </span>
            <span
              v-if="isPublished"
              class="inline-flex items-center px-2.5 py-1 rounded-full text-[.8rem] font-semibold"
              :class="event.is_public ? 'bg-[#f3f0ff] text-[#6352e7]' : 'bg-[#f1f1f5] text-muted'"
            >{{ event.is_public ? 'Public' : 'Private' }}</span>
          </div>
          <div class="font-bold text-lg text-ink">{{ event.name }}</div>
          <div class="muted text-[.84rem] mt-0.5">
            {{ isPublished ? `Published ${fmtDateTime(event.published_at)}` : 'Not published yet.' }}
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="!isPublished"
            class="btn"
            :disabled="busy"
            @click="setStatus('published')"
          >
            {{ busy ? 'Publishing…' : 'PUBLISH EVENT' }}
          </button>
          <button
            v-else
            class="btn ghost"
            :disabled="busy"
            @click="setStatus('draft')"
          >
            {{ busy ? 'Working…' : 'Unpublish' }}
          </button>
        </div>
      </div>

      <!-- Not-ready hint -->
      <div v-if="!isPublished && !allReady" class="mt-4 p-3 rounded-lg bg-amber-50 text-amber-800 text-[.84rem]">
        ⚠ {{ readyCount }} of {{ total }} setup steps complete. You can still publish, but finishing the checklist below is recommended.
      </div>
    </div>

    <!-- Visibility (only meaningful once published) -->
    <div v-if="isPublished" class="card mb-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <div class="font-bold text-base">Visibility</div>
          <div class="muted text-[.84rem]">
            {{ event.is_public ? 'Anyone with the link can view the event website.' : 'Only people you invite can access the event.' }}
          </div>
        </div>
        <button
          class="relative w-12 h-[26px] rounded-full transition-colors duration-150 shrink-0 border-0 cursor-pointer"
          :class="event.is_public ? 'bg-[#6352e7]' : 'bg-[#cbd0da]'"
          :disabled="busy"
          @click="setVisibility(!event.is_public)"
        >
          <span
            class="absolute top-[3px] w-5 h-5 rounded-full bg-white shadow transition-all duration-150"
            :class="event.is_public ? 'left-[25px]' : 'left-[3px]'"
          />
        </button>
      </div>
    </div>

    <!-- Website URL -->
    <div class="card mb-4">
      <div class="font-bold text-base mb-1">Event Website</div>
      <div class="muted text-[.84rem] mb-3">The address where attendees reach your event.</div>
      <div class="flex items-center gap-2 flex-wrap">
        <code class="flex-1 min-w-[220px] px-3 py-2.5 rounded-lg bg-[#f7f7fa] border border-line text-[.86rem] text-ink truncate">{{ websiteUrl }}</code>
        <button class="btn ghost px-3" @click="copyUrl">{{ copied ? '✓ Copied' : 'Copy' }}</button>
        <a
          :href="websiteUrl" target="_blank" rel="noopener"
          class="btn px-3 no-underline"
          :class="{ 'opacity-50 pointer-events-none': !isPublished }"
        >Visit ↗</a>
      </div>
      <p class="muted text-[.8rem] mt-2 mb-0">
        Configure the subdomain or a custom domain under
        <component :is="NuxtLink" :to="`/org/events/${id}/settings/domain`" class="text-brand font-semibold">Settings → Domain</component>.
      </p>
    </div>

    <!-- Pre-publish checklist -->
    <div class="card">
      <div class="flex items-center gap-3 mb-3">
        <div class="font-bold text-base">Readiness Checklist</div>
        <span class="muted text-[.84rem]">{{ completed }}/{{ total }} complete</span>
        <div class="flex-1 h-2 bg-[#eef0f4] rounded-full overflow-hidden">
          <div class="h-full bg-brand transition-all duration-300" :style="{ width: `${total ? (completed / total) * 100 : 0}%` }" />
        </div>
      </div>

      <component
        :is="sectionLink(item.to) ? NuxtLink : 'div'"
        v-for="item in checklist" :key="item.key"
        :to="sectionLink(item.to)"
        class="flex items-center gap-3 p-3 border border-line rounded-xl mb-2 bg-[#fbfbfd] no-underline hover:bg-[#f6f7f9]"
      >
        <span
          class="w-[22px] h-[22px] rounded-md border-2 border-[#cdd2dc] grid place-items-center text-white text-[.8rem] shrink-0"
          :class="{ 'bg-brand border-brand': item.done }"
        >{{ item.done ? '✓' : '' }}</span>
        <span class="flex-1 text-ink font-[550]">{{ item.label }}</span>
        <span v-if="sectionLink(item.to)" class="text-brand font-bold">→</span>
      </component>
    </div>
  </div>

  <p v-else class="muted">Loading…</p>
</template>
