<script setup lang="ts">
import type { SurveyAnswer } from '~/stores/surveys'

definePageMeta({ layout: 'event', middleware: 'auth' })

const surveys = useSurveysStore()

const FILTERS: { key: 'open' | 'answered' | 'all', label: string }[] = [
  { key: 'open', label: 'To answer' },
  { key: 'answered', label: 'Answered' },
  { key: 'all', label: 'All' },
]

const modal = ref<{ showError: (m: string) => void } | null>(null)

const emptyLabel = computed(() => {
  if (!surveys.surveys.length) return 'No surveys have been published yet. Check back soon.'
  if (surveys.filter === 'open') return 'Nothing to answer right now — you’re all caught up.'
  if (surveys.filter === 'answered') return 'You haven’t answered a survey yet.'
  return 'No surveys yet.'
})

async function submit(answers: Record<number, SurveyAnswer>) {
  const id = surveys.current?.id
  if (!id) return

  try {
    await surveys.submit(id, answers)
  } catch (e: any) {
    const data = e?.data
    // Laravel returns the first field error under `errors`; fall back to message.
    const first = data?.errors ? (Object.values(data.errors)[0] as string[])?.[0] : null
    modal.value?.showError(first || data?.message || 'Your answers couldn’t be sent. Please try again.')
  }
}

onMounted(() => { if (!surveys.loaded) surveys.fetchSurveys() })
</script>

<template>
  <div>
    <div class="head">
      <h1>Surveys</h1>
      <p class="sub">Tell the organizers what you think — it takes a minute.</p>
    </div>

    <div class="filters">
      <button
        v-for="f in FILTERS" :key="f.key"
        type="button"
        class="chip"
        :class="{ on: surveys.filter === f.key }"
        @click="surveys.filter = f.key"
      >
        {{ f.label }}
        <span class="n">{{ surveys.counts[f.key] }}</span>
      </button>
    </div>

    <div v-if="surveys.loading && !surveys.loaded" class="state">Loading surveys…</div>
    <div v-else-if="surveys.error" class="state">Couldn’t load surveys. Please try again.</div>
    <div v-else-if="!surveys.shown.length" class="state">{{ emptyLabel }}</div>

    <div v-else class="grid">
      <SurveysSurveyCard
        v-for="s in surveys.shown" :key="s.id"
        :survey="s"
        @open="surveys.openSurvey($event)"
      />
    </div>

    <SurveysSurveyModal
      v-if="surveys.current"
      ref="modal"
      :survey="surveys.current"
      :loading="surveys.currentLoading"
      :submitting="surveys.submitting"
      @close="surveys.close()"
      @submit="submit"
    />
  </div>
</template>

<style scoped>
.head { margin-bottom: 16px; }
.head h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }
.sub { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
.state { padding: 60px 0; text-align: center; color: #64748b; }

.filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
.chip {
  display: inline-flex; align-items: center; gap: 7px; background: #fff; border: 1px solid #e2e8f0;
  border-radius: 999px; padding: 7px 14px; font: inherit; font-size: .82rem; font-weight: 600;
  color: #475569; cursor: pointer;
}
.chip:hover { border-color: var(--brand-primary); color: var(--brand-primary); }
.chip.on { background: var(--brand-primary); border-color: var(--brand-primary); color: #fff; }
.chip .n {
  min-width: 18px; padding: 0 5px; border-radius: 999px; background: #f1f5f9; color: #64748b;
  font-size: .72rem; font-weight: 700; text-align: center;
}
.chip.on .n { background: rgba(255,255,255,.25); color: #fff; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
</style>
