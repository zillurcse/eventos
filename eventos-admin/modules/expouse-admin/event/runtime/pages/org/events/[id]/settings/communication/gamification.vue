<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Action { key: string, label: string, column: 'left' | 'right', once?: boolean }

const actions = ref<Action[]>([])
const enabled = ref(false)
const scores = reactive<Record<string, number>>({})
const award = reactive({ title: '', description: '' })
const saving = ref(false)
const loading = ref(true)

const leftActions = computed(() => actions.value.filter(a => a.column === 'left'))
const rightActions = computed(() => actions.value.filter(a => a.column === 'right'))

function seedScores(list: Action[], values: Record<string, number> = {}) {
  for (const a of list) {
    scores[a.key] = Number.isFinite(values[a.key]) ? Number(values[a.key]) : 1
  }
}

async function load() {
  loading.value = true
  try {
    const res = await api<{
      data: {
        enabled: boolean
        scores: Record<string, number>
        actions: Action[]
        award_title: string | null
        award_description: string | null
      }
    }>(`/events/${id}/gamification`)

    actions.value = Array.isArray(res.data.actions) ? res.data.actions : []
    enabled.value = res.data.enabled
    seedScores(actions.value, res.data.scores || {})
    award.title = res.data.award_title || ''
    award.description = res.data.award_description || ''
  } catch {
    actions.value = []
    seedScores([])
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const clean: Record<string, number> = {}
    for (const a of actions.value) {
      clean[a.key] = Math.max(0, Math.trunc(Number(scores[a.key]) || 0))
    }
    await api(`/events/${id}/gamification`, {
      method: 'PUT',
      body: {
        enabled: enabled.value,
        scores: clean,
        award_title: award.title.trim() || null,
        award_description: award.description || null,
      },
    })
    toast.success('Gamification saved')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <h1 class="text-[1.4rem] font-bold text-ink mb-4">Communication</h1>
    <CommunicationTabs :event-id="id" active="gamification" />

    <!-- Header card with the master toggle -->
    <div class="card mb-4">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="font-bold text-base text-ink m-0">
            Gamification
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
      <!-- Score matrix — actions come from the API catalogue -->
      <div class="card mb-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-bold text-base text-ink m-0">Point Scoring</h3>
          <span class="badge">{{ actions.length }} actions</span>
        </div>
        <div v-if="loading" class="muted text-[.86rem] py-6">Loading scoring actions…</div>
        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-x-10 gap-y-0">
          <div v-for="(col, ci) in [leftActions, rightActions]" :key="ci">
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
          <SessionDescriptionEditor v-model="award.description" />
        </FormField>
      </div>
    </template>

    <div class="flex justify-end">
      <button class="btn" :disabled="saving || loading" @click="save">
        {{ saving ? 'Saving…' : 'Save' }}
      </button>
    </div>
  </div>
</template>
