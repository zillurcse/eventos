<script setup lang="ts">
import type { Survey, SurveyAnswer, SurveyQuestion } from '~/stores/surveys'

/**
 * Fill in a survey — or, once it has been answered, read back what was sent.
 * Question types mirror the organizer's builder (Engagement › Surveys).
 */
const props = defineProps<{ survey: Survey, loading: boolean, submitting: boolean }>()
const emit = defineEmits<{ close: [], submit: [answers: Record<number, SurveyAnswer>] }>()

const answers = reactive<Record<number, SurveyAnswer>>({})
const fileNames = reactive<Record<number, string>>({})
const uploading = ref<number | null>(null)
const errorMsg = ref('')
const missing = ref<number[]>([])

const surveys = useSurveysStore()

/** Read-only once answered — a survey takes one response per attendee. */
const readonly = computed(() => props.survey.has_responded)

/** Seed the form: empty for a new response, my answers when reviewing one. */
watch(() => [props.survey.id, props.survey.questions, props.survey.my_answers], () => {
  for (const key of Object.keys(answers)) delete answers[Number(key)]
  for (const q of props.survey.questions ?? []) {
    const mine = props.survey.my_answers?.[q.id]
    answers[q.id] = mine ?? (q.type === 'multiselect' ? [] : '')
  }
}, { immediate: true, deep: false })

function toggleChoice(q: SurveyQuestion, value: string) {
  const picked = Array.isArray(answers[q.id]) ? [...(answers[q.id] as string[])] : []
  const at = picked.indexOf(value)
  at === -1 ? picked.push(value) : picked.splice(at, 1)
  answers[q.id] = picked
}

const isPicked = (q: SurveyQuestion, value: string) =>
  Array.isArray(answers[q.id]) && (answers[q.id] as string[]).includes(value)

async function pickFile(q: SurveyQuestion, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploading.value = q.id
  errorMsg.value = ''
  try {
    const res = await surveys.uploadFile(file)
    answers[q.id] = res.url
    fileNames[q.id] = res.filename || file.name
  } catch {
    errorMsg.value = 'That file couldn’t be uploaded. Please try another.'
  } finally {
    uploading.value = null
    input.value = ''
  }
}

const isBlank = (v: SurveyAnswer) =>
  v === null || v === undefined || (Array.isArray(v) ? v.length === 0 : String(v).trim() === '')

/** The label of a picked option, for the read-back view. */
function optionLabel(q: SurveyQuestion, value: string) {
  return q.options.find(o => o.value === value)?.label ?? value
}

function answerText(q: SurveyQuestion): string {
  const v = answers[q.id]
  if (isBlank(v)) return '—'
  if (Array.isArray(v)) return v.map(x => optionLabel(q, x)).join(', ')
  if (q.type === 'select' || q.type === 'radio') return optionLabel(q, String(v))
  if (q.type === 'date') return new Date(String(v)).toLocaleDateString()
  return String(v)
}

function send() {
  missing.value = props.survey.questions
    .filter(q => q.is_required && isBlank(answers[q.id]))
    .map(q => q.id)

  if (missing.value.length) {
    errorMsg.value = `Please answer the ${missing.value.length === 1 ? 'question' : 'questions'} marked below.`
    document.getElementById(`q-${missing.value[0]}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  errorMsg.value = ''
  // Drop the blanks so optional questions don't record empty strings.
  const payload: Record<number, SurveyAnswer> = {}
  for (const q of props.survey.questions) {
    if (!isBlank(answers[q.id])) payload[q.id] = answers[q.id]
  }
  emit('submit', payload)
}

/** Surface the API's own message (closed survey, already answered, …). */
function showError(message: string) {
  errorMsg.value = message
}
defineExpose({ showError })
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="survey-title">
      <div class="head">
        <div class="ht">
          <h2 id="survey-title">{{ survey.title }}</h2>
          <p v-if="survey.is_anonymous" class="anon">Your answers are anonymous</p>
        </div>
        <button type="button" class="x" aria-label="Close" @click="emit('close')">
          <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" /></svg>
        </button>
      </div>

      <div class="body">
        <div v-if="loading && !survey.questions?.length" class="state">Loading survey…</div>

        <template v-else>
          <p v-if="survey.description" class="intro">{{ survey.description }}</p>

          <div v-if="readonly" class="done-note">
            <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
            Thanks — your response has been recorded.
          </div>

          <div v-if="!survey.questions?.length" class="state">
            This survey has no questions yet.
          </div>

          <!-- Read-back of a submitted response -->
          <dl v-else-if="readonly" class="review">
            <template v-for="q in survey.questions" :key="q.id">
              <dt>{{ q.label }}</dt>
              <dd>
                <a v-if="q.type === 'file' && !isBlank(answers[q.id])" :href="String(answers[q.id])" target="_blank" rel="noopener">
                  View uploaded file
                </a>
                <template v-else>{{ answerText(q) }}</template>
              </dd>
            </template>
          </dl>

          <!-- The questionnaire -->
          <form v-else class="form" @submit.prevent="send">
            <fieldset
              v-for="(q, i) in survey.questions" :key="q.id"
              :id="`q-${q.id}`"
              class="q"
              :class="{ bad: missing.includes(q.id) }"
            >
              <legend>
                <span class="n">{{ i + 1 }}</span>
                {{ q.label }}
                <em v-if="q.is_required" aria-hidden="true">*</em>
              </legend>
              <p v-if="q.help_text" class="help">{{ q.help_text }}</p>

              <textarea
                v-if="q.type === 'textarea'"
                v-model="answers[q.id]"
                rows="4"
                maxlength="5000"
                placeholder="Type your answer"
              />

              <input
                v-else-if="q.type === 'date'"
                v-model="answers[q.id]"
                type="date"
              >

              <select v-else-if="q.type === 'select'" v-model="answers[q.id]">
                <option value="">Choose an option</option>
                <option v-for="o in q.options" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>

              <div v-else-if="q.type === 'radio'" class="choices">
                <label v-for="o in q.options" :key="o.value" class="choice">
                  <input v-model="answers[q.id]" type="radio" :name="`q-${q.id}`" :value="o.value">
                  <span>{{ o.label }}</span>
                </label>
              </div>

              <div v-else-if="q.type === 'multiselect'" class="choices">
                <label v-for="o in q.options" :key="o.value" class="choice">
                  <input type="checkbox" :checked="isPicked(q, o.value)" @change="toggleChoice(q, o.value)">
                  <span>{{ o.label }}</span>
                </label>
              </div>

              <div v-else-if="q.type === 'file'" class="file">
                <label class="upload">
                  <input type="file" hidden @change="pickFile(q, $event)">
                  {{ uploading === q.id ? 'Uploading…' : (isBlank(answers[q.id]) ? 'Choose a file' : 'Replace file') }}
                </label>
                <a v-if="!isBlank(answers[q.id])" :href="String(answers[q.id])" target="_blank" rel="noopener" class="fname">
                  {{ fileNames[q.id] || 'Uploaded file' }}
                </a>
              </div>

              <input
                v-else
                v-model="answers[q.id]"
                type="text"
                maxlength="1000"
                placeholder="Type your answer"
              >
            </fieldset>
          </form>
        </template>

        <p v-if="errorMsg" class="err">{{ errorMsg }}</p>
      </div>

      <div class="foot">
        <button type="button" class="ghost" @click="emit('close')">
          {{ readonly ? 'Close' : 'Cancel' }}
        </button>
        <button
          v-if="!readonly && survey.questions?.length"
          type="button"
          class="send"
          :disabled="submitting || uploading !== null"
          @click="send"
        >
          {{ submitting ? 'Sending…' : 'Submit answers' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 60; }
.modal { background: #fff; border-radius: 18px; width: 100%; max-width: 560px; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.28); }

.head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 18px 20px; border-bottom: 1px solid #eef0f3; }
.ht h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #1e293b; }
.anon { margin: 4px 0 0; font-size: .78rem; color: var(--brand-primary); font-weight: 600; }
.x { border: none; background: #f1f5f9; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.x svg { width: 16px; height: 16px; fill: none; stroke: #64748b; stroke-width: 2; stroke-linecap: round; }

.body { padding: 18px 20px; overflow-y: auto; }
.state { padding: 28px 0; text-align: center; color: #94a3b8; font-size: .88rem; }
.intro { margin: 0 0 14px; color: #64748b; font-size: .88rem; line-height: 1.5; }

.done-note { display: flex; align-items: center; gap: 8px; background: #f0fdf4; color: #15803d; border-radius: 12px; padding: 10px 14px; font-size: .85rem; font-weight: 600; margin-bottom: 14px; }
.done-note svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }

.review { margin: 0; }
.review dt { font-size: .82rem; font-weight: 700; color: #334155; margin-bottom: 4px; }
.review dd { margin: 0 0 14px; font-size: .9rem; color: #64748b; line-height: 1.5; }
.review a { color: var(--brand-primary); font-weight: 600; }

.form { display: flex; flex-direction: column; gap: 18px; }
.q { border: none; margin: 0; padding: 0; }
.q legend { display: flex; align-items: baseline; gap: 8px; font-size: .9rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; padding: 0; }
.q legend .n { flex: 0 0 auto; width: 22px; height: 22px; border-radius: 50%; background: #f1f5f9; color: #64748b; font-size: .72rem; display: inline-flex; align-items: center; justify-content: center; }
.q legend em { color: #dc2626; font-style: normal; }
.q.bad legend .n { background: #fee2e2; color: #dc2626; }
.help { margin: -4px 0 8px 30px; font-size: .8rem; color: #94a3b8; }

.q input[type="text"], .q input[type="date"], .q textarea, .q select {
  width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 11px 13px;
  font: inherit; font-size: .9rem; outline: none; resize: vertical; background: #fff; color: #1e293b;
}
.q input:focus, .q textarea:focus, .q select:focus { border-color: var(--brand-primary); }
.q.bad input, .q.bad textarea, .q.bad select { border-color: #fca5a5; }

.choices { display: flex; flex-direction: column; gap: 6px; }
.choice { display: flex; align-items: center; gap: 10px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; cursor: pointer; font-size: .89rem; color: #334155; }
.choice:hover { border-color: var(--brand-primary); }
.choice input { accent-color: var(--brand-primary); width: 16px; height: 16px; }

.file { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.upload { border: 1px dashed #cbd5e1; border-radius: 10px; padding: 10px 16px; font-size: .85rem; font-weight: 600; color: #475569; cursor: pointer; }
.upload:hover { border-color: var(--brand-primary); color: var(--brand-primary); }
.fname { font-size: .82rem; color: var(--brand-primary); font-weight: 600; word-break: break-all; }

.err { margin: 14px 0 0; color: #dc2626; font-size: .85rem; }

.foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; border-top: 1px solid #eef0f3; }
.ghost { border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 10px; padding: 10px 18px; font: inherit; font-size: .86rem; font-weight: 700; cursor: pointer; }
.send { border: none; background: var(--brand-primary); color: #fff; border-radius: 10px; padding: 10px 18px; font: inherit; font-size: .86rem; font-weight: 700; cursor: pointer; }
.send:disabled { opacity: .6; cursor: default; }
</style>
