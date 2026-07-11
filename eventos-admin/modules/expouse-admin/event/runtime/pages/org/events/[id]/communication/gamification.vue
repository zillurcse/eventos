<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Action { key: string, label: string }

// Fixed catalogue of point-scoring actions, split into the two columns shown
// in the UI. Scores are stored as a { key => points } map.
const LEFT_ACTIONS: Action[] = [
  { key: 'create_account', label: 'Create account' },
  { key: 'complete_onboarding', label: 'Complete onboarding' },
  { key: 'attend_welcoming_video', label: 'Attend welcoming video' },
  { key: 'create_feed_text_post', label: 'Create feed text post' },
  { key: 'create_feed_image_post', label: 'Create feed image post' },
  { key: 'create_feed_video_post', label: 'Create feed video post' },
  { key: 'create_feed_polls_post', label: 'Create feed polls post' },
  { key: 'create_feed_offering_post', label: 'Create feed offering post' },
  { key: 'create_feed_looking_for_post', label: 'Create feed looking for post' },
  { key: 'comment_feed_post', label: 'Comment feed post' },
  { key: 'feed_post_likes', label: 'Feed post likes' },
  { key: 'vote_feed_polls', label: 'Vote feed Polls' },
  { key: 'sessions_agenda_chat', label: 'Sessions/ Agenda chat' },
  { key: 'create_sessions_agenda_qa', label: 'Create sessions/ Agenda Q&A' },
  { key: 'create_sessions_agenda_polls', label: 'Create sessions/ Agenda polls' },
  { key: 'vote_sessions_agenda_polls', label: 'Vote sessions/ Agenda polls' },
  { key: 'rate_session', label: 'Rate session' },
  { key: 'attend_lounge_meeting', label: 'Attend lounge meeting' },
  { key: 'lounge_meeting_feedback', label: 'Lounge meeting feedback' },
]

const RIGHT_ACTIONS: Action[] = [
  { key: 'attend_breakout_rooms_meeting', label: 'Attend breakout rooms meeting' },
  { key: 'breakout_rooms_meeting_feedback', label: 'Breakout rooms meeting feedback' },
  { key: 'visit_exhibitor_profile', label: 'Visit exhibitor profile' },
  { key: 'visit_exhibitor_social_media', label: 'Visit exhibitor social media' },
  { key: 'rate_exhibitor', label: 'Rate exhibitor' },
  { key: 'chat_with_exhibitor_representative', label: 'Chat with exhibitor representative' },
  { key: 'meet_exhibitor_representative', label: 'Meet exhibitor representative' },
  { key: 'exhibitor_representative_meeting_feedback', label: 'Exhibitor representative meeting feedback' },
  { key: 'attend_exhibitor_displayed_videos', label: 'Attend exhibitor displayed videos' },
  { key: 'attend_exhibitor_displayed_images', label: 'Attend exhibitor displayed images' },
  { key: 'visit_sponsor_profile', label: 'Visit sponsor profile' },
  { key: 'visit_sponsor_social_media', label: 'Visit sponsor social media' },
  { key: 'chat_with_sponsor_representative', label: 'Chat with sponsor representative' },
  { key: 'meet_sponsor_representative', label: 'Meet sponsor representative' },
  { key: 'exhibitor_sponsor_meeting_feedback', label: 'Exhibitor sponsor meeting feedback' },
  { key: 'attend_sponsor_displayed_videos', label: 'Attend sponsor displayed videos' },
  { key: 'chat_with_delegates', label: 'Chat with delegates' },
  { key: 'meet_delegates', label: 'Meet delegates' },
  { key: 'view_speakers_profile', label: 'View speakers profile' },
]

const ALL_ACTIONS = [...LEFT_ACTIONS, ...RIGHT_ACTIONS]

const enabled = ref(false)
const scores = reactive<Record<string, number>>({})
const award = reactive({ title: '', description: '' })

const saving = ref(false)
const saved = ref(false)

function seedScores(values: Record<string, number> = {}) {
  for (const a of ALL_ACTIONS) {
    scores[a.key] = Number.isFinite(values[a.key]) ? values[a.key] : 1
  }
}

async function load() {
  try {
    const res = await api<{ data: { enabled: boolean, scores: Record<string, number>, award_title: string | null, award_description: string | null } }>(`/events/${id}/gamification`)
    enabled.value = res.data.enabled
    seedScores(res.data.scores || {})
    award.title = res.data.award_title || ''
    award.description = res.data.award_description || ''
  } catch {
    seedScores()
  }
}

async function save() {
  saving.value = true
  try {
    const clean: Record<string, number> = {}
    for (const a of ALL_ACTIONS) clean[a.key] = Math.max(0, Math.trunc(Number(scores[a.key]) || 0))
    await api(`/events/${id}/gamification`, {
      method: 'PUT',
      body: {
        enabled: enabled.value,
        scores: clean,
        award_title: award.title.trim() || null,
        award_description: award.description || null,
      },
    })
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Header card with the master toggle -->
    <div class="card mb-4">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="font-bold text-base text-ink m-0">
            Gamification
            <span v-if="saved" class="badge active ml-2">saved ✓</span>
          </h2>
          <p class="muted text-[.86rem] mt-1 mb-0 max-w-[820px]">
            Facilitate a friendly fun gaming networking between users to boost the users with an action-based
            point &amp; reward system. Leaderboard will be displayed for end-of-day top scorers.
          </p>
        </div>
        <button
          type="button"
          role="switch"
          :aria-checked="enabled"
          class="relative w-11 h-6 rounded-full shrink-0 transition-colors duration-150"
          :class="enabled ? 'bg-brand' : 'bg-[#d1d5db]'"
          @click="enabled = !enabled"
        >
          <span
            class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform duration-150"
            :class="enabled ? 'translate-x-5' : ''"
          />
        </button>
      </div>
    </div>

    <template v-if="enabled">
      <!-- Score matrix -->
      <div class="card mb-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-bold text-base text-ink m-0">Point Scoring</h3>
          <span class="badge">{{ ALL_ACTIONS.length }} actions</span>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 gap-y-0">
          <div v-for="(col, ci) in [LEFT_ACTIONS, RIGHT_ACTIONS]" :key="ci">
            <div class="flex items-center justify-between pb-2 mb-1 border-b border-line">
              <span class="text-[.76rem] font-bold text-muted uppercase tracking-wide">When an attendee</span>
              <span class="text-[.76rem] font-bold text-muted uppercase tracking-wide">Score</span>
            </div>
            <div
              v-for="a in col" :key="a.key"
              class="flex items-center justify-between gap-3 py-2 px-1 -mx-1 rounded-lg border-b border-[#f1f1f5] last:border-0 hover:bg-[#f8f9fc] transition-colors"
            >
              <label :for="`sc-${a.key}`" class="text-[.86rem] text-ink m-0">{{ a.label }}</label>
              <input
                :id="`sc-${a.key}`"
                v-model.number="scores[a.key]"
                type="number"
                min="0"
                class="m-0 w-16 text-center font-semibold"
              >
            </div>
          </div>
        </div>
      </div>

      <!-- Award -->
      <div class="card mb-5">
        <h3 class="font-bold text-base text-ink m-0 mb-1">Award</h3>
        <p class="muted text-[.84rem] m-0 mb-4">Award will appear at the event login page.</p>

        <AppInput v-model="award.title" label="Title" placeholder="Enter Title" />

        <FormField label="Description" class="mt-3">
          <SessionDescriptionEditor v-model="award.description"  />
        </FormField>
      </div>
    </template>

    <div class="flex justify-end">
      <button class="btn" :disabled="saving" @click="save">
        {{ saving ? 'Saving…' : 'Save' }}
      </button>
    </div>
  </div>
</template>
