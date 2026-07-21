<script setup lang="ts">
import type { Survey } from '~/stores/surveys'

/** One survey in the Surveys grid. Opens the questionnaire in a modal. */
const props = defineProps<{ survey: Survey }>()
const emit = defineEmits<{ open: [id: number] }>()

const PHASE_LABEL = { ongoing: 'Open', upcoming: 'Upcoming', ended: 'Closed' } as const

const when = computed(() => contestWindow(props.survey.opens_at, props.survey.closes_at))

/** The one line that tells the attendee what to do next. */
const cta = computed(() => {
  const s = props.survey
  if (s.has_responded) return 'View my answers'
  if (s.phase === 'upcoming') return `Opens ${contestWhen(s.opens_at)}`
  if (s.phase === 'ended') return 'Closed — you didn’t answer'
  if (!s.questions_count) return 'No questions yet'
  return 'Answer survey'
})
</script>

<template>
  <div class="card" :class="{ done: survey.has_responded }">
    <div class="top">
      <h3>{{ survey.title }}</h3>
      <span class="phase" :class="survey.phase">{{ PHASE_LABEL[survey.phase] }}</span>
    </div>

    <p v-if="survey.description" class="desc">{{ survey.description }}</p>

    <div class="meta">
      <span class="pill">{{ survey.questions_count }} {{ survey.questions_count === 1 ? 'question' : 'questions' }}</span>
      <span v-if="survey.is_anonymous" class="pill anon">Anonymous</span>
      <span v-if="survey.has_responded" class="pill done">Answered</span>
    </div>

    <p v-if="when" class="when">{{ when }}</p>

    <button
      type="button"
      class="act"
      :class="{ primary: survey.can_respond }"
      :disabled="!survey.can_respond && !survey.has_responded"
      @click="emit('open', survey.id)"
    >
      {{ cta }}
    </button>
  </div>
</template>

<style scoped>
.card {
  background: #fff; border-radius: 16px; padding: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05);
  display: flex; flex-direction: column; transition: box-shadow .15s, transform .15s;
}
.card:hover { box-shadow: 0 6px 18px rgba(15,23,42,.09); transform: translateY(-2px); }

.top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.top h3 { margin: 0 0 6px; font-size: 1rem; font-weight: 700; color: #1e293b; }
.phase {
  flex: 0 0 auto; padding: 3px 10px; border-radius: 999px; background: #f1f5f9; color: #64748b;
  font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
}
.phase.ongoing { background: #dcfce7; color: #15803d; }
.phase.upcoming { background: #dbeafe; color: #1d4ed8; }

.desc {
  margin: 0 0 10px; color: #64748b; font-size: .84rem; line-height: 1.45;
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}

.meta { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.pill { background: #f1f5f9; color: #475569; border-radius: 999px; padding: 3px 10px; font-size: .72rem; font-weight: 700; }
.pill.anon { background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); }
.pill.done { background: #dcfce7; color: #15803d; }

.when { margin: 0 0 12px; color: #94a3b8; font-size: .78rem; font-weight: 600; }

.act {
  margin-top: auto; width: 100%; border: 1px solid #e2e8f0; background: #fff; color: #475569;
  border-radius: 10px; padding: 10px 14px; font: inherit; font-size: .84rem; font-weight: 700; cursor: pointer;
}
.act:hover:not(:disabled) { border-color: var(--brand-primary); color: var(--brand-primary); }
.act.primary { background: var(--brand-primary); border-color: var(--brand-primary); color: #fff; }
.act.primary:hover { filter: brightness(1.06); color: #fff; }
.act:disabled { cursor: default; color: #94a3b8; background: #f8fafc; }
</style>
