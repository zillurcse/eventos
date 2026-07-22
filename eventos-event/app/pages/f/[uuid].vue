<script setup lang="ts">
/**
 * Standalone public form — the shareable / embeddable face of an organizer's
 * profile form (Event Settings › Profile › Publish & Share).
 *
 * Reached two ways, both with the form uuid as the render token:
 *   /f/{uuid}          — direct share link (full branded page)
 *   /f/{uuid}?embed=1  — inside an <iframe> on the organizer's own website
 *                        (compact chrome, no cover header)
 *
 * No auth, no subdomain requirement: GET /forms/{uuid} and
 * POST /forms/{uuid}/submit are public; the uuid itself is the capability.
 */
definePageMeta({ layout: false })

const route = useRoute()
const api = useApi()
const uuid = route.params.uuid as string
const embedded = computed(() => route.query.embed === '1' || route.query.embed === 'true')

interface PublicField {
  key: string
  label: string | null
  help_text: string | null
  type: string
  required: boolean
  validation: Record<string, any> | null
  meta: {
    placeholder?: string
    width?: number
    visible?: boolean
    surfaces?: Record<string, boolean>
  } | null
  options: { label: string, value: string | null }[]
}

const form = ref<any | null>(null)
const eventInfo = ref<any | null>(null)
const state = ref<'loading' | 'ready' | 'missing' | 'done'>('loading')

const values = reactive<Record<string, any>>({})
const errors = reactive<Record<string, string>>({})
const hp = ref('')            // honeypot — humans never see it
const robot = ref(false)      // reCAPTCHA-style confirmation, when the form has one
const submitting = ref(false)

const fields = computed<PublicField[]>(() =>
  (form.value?.fields || []).filter((f: PublicField) =>
    (f.meta?.visible ?? true) !== false
    && ((f.meta?.surfaces as any)?.public ?? true) !== false,
  ),
)

const hasCaptcha = computed(() => fields.value.some(f => f.type === 'recaptcha'))

/**
 * Appearance the organizer chose in the builder's Design tab
 * (forms.settings.design). A brand_color of null means "follow the event",
 * so a rebrand carries here without editing the form.
 */
const design = computed(() => ({
  background_type: 'color',
  background_color: '#f1f3f9',
  background_image_url: null as string | null,
  brand_color: null as string | null,
  card_style: 'solid',
  show_header: true,
  ...(form.value?.settings?.design || {}),
}))

const brand = computed(() => design.value.brand_color || eventInfo.value?.primary || '#6352e7')

const bgImage = computed(() =>
  design.value.background_type === 'image' ? design.value.background_image_url : null)

// Embeds sit inside someone else's page, so they stay transparent and let the
// host site's own background show through — that's the point of embedding.
const pageStyle = computed(() => {
  if (embedded.value) return {}
  return bgImage.value
    ? { backgroundImage: `url('${bgImage.value}')`, backgroundSize: 'cover', backgroundPosition: 'center', backgroundAttachment: 'fixed' }
    : { background: design.value.background_color }
})

const glass = computed(() => design.value.card_style === 'glass')
const showHeader = computed(() => design.value.show_header !== false && !embedded.value)

const OPTIONED = ['select', 'multiselect', 'radio', 'checkbox']
const ARRAY_TYPES = ['multiselect', 'checkbox']

onMounted(async () => {
  try {
    const r = await api<any>(`/forms/${uuid}`)
    form.value = r.data
    eventInfo.value = r.event
    for (const f of fields.value) {
      if (ARRAY_TYPES.includes(f.type)) values[f.key] = []
      else if (f.type !== 'section_break' && f.type !== 'recaptcha') values[f.key] = ''
    }
    state.value = 'ready'
  } catch {
    state.value = 'missing'
  }
})

function toggleArrayValue(key: string, v: string) {
  const arr: string[] = values[key] || (values[key] = [])
  const i = arr.indexOf(v)
  i >= 0 ? arr.splice(i, 1) : arr.push(v)
}

const fmtDates = computed(() => {
  const s = eventInfo.value?.starts_at ? new Date(eventInfo.value.starts_at) : null
  if (!s) return ''
  return s.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' })
})

function validate(): boolean {
  Object.keys(errors).forEach(k => delete errors[k])
  for (const f of fields.value) {
    if (f.type === 'section_break' || f.type === 'recaptcha' || !f.required) continue
    const v = values[f.key]
    const empty = Array.isArray(v) ? !v.length : (v === '' || v === null || v === undefined)
    if (empty) errors[f.key] = `${f.label || 'This field'} is required.`
  }
  return !Object.keys(errors).length
}

async function submit() {
  if (submitting.value) return
  if (!validate()) return
  if (hasCaptcha.value && !robot.value) {
    errors._captcha = 'Please confirm you are not a robot.'
    return
  }
  delete errors._captcha

  submitting.value = true
  try {
    const body: Record<string, any> = { _source: embedded.value ? 'embed' : 'link', _hp: hp.value }
    for (const f of fields.value) {
      if (f.type === 'section_break' || f.type === 'recaptcha') continue
      let v = values[f.key]
      if (v === '' || v === null || v === undefined || (Array.isArray(v) && !v.length)) continue
      if (f.type === 'number' || f.type === 'rating') v = Number(v)
      body[f.key] = v
    }
    await api(`/forms/${uuid}/submit`, { method: 'POST', body })
    state.value = 'done'
  } catch (e: any) {
    const server = e?.data?.errors || {}
    for (const [k, msgs] of Object.entries(server)) errors[k] = Array.isArray(msgs) ? String(msgs[0]) : String(msgs)
    if (!Object.keys(server).length) errors._top = e?.data?.message || 'Something went wrong — please try again.'
  } finally { submitting.value = false }
}
</script>

<template>
  <div class="page" :class="{ embedded, glass }" :style="{ '--bp': brand, ...pageStyle }">
    <!-- ── Loading / missing ─────────────────────────────────── -->
    <div v-if="state === 'loading'" class="center muted-block">Loading form…</div>

    <div v-else-if="state === 'missing'" class="center">
      <div class="panel narrow">
        <h1 class="miss-title">Form not available</h1>
        <p class="miss-text">This form doesn't exist or hasn't been published yet. Please check the link with the event organizer.</p>
      </div>
    </div>

    <!-- ── Success ───────────────────────────────────────────── -->
    <div v-else-if="state === 'done'" class="center">
      <div class="panel narrow done">
        <div class="done-icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
        </div>
        <h1 class="miss-title">Thank you!</h1>
        <p class="miss-text">
          Your response has been recorded<span v-if="eventInfo?.name"> for <strong>{{ eventInfo.name }}</strong></span>.
          The event team will review it and get back to you.
        </p>
      </div>
    </div>

    <!-- ── Form ──────────────────────────────────────────────── -->
    <template v-else>
      <header v-if="showHeader" class="hero" :style="eventInfo?.cover_url ? { backgroundImage: `linear-gradient(rgba(15,23,42,.55), rgba(15,23,42,.72)), url('${eventInfo.cover_url}')` } : {}">
        <img v-if="eventInfo?.logo_url" :src="eventInfo.logo_url" :alt="eventInfo?.name" class="hero-logo">
        <h1>{{ eventInfo?.name || form?.name }}</h1>
        <p v-if="fmtDates" class="hero-sub">{{ fmtDates }}<span v-if="eventInfo?.location"> · {{ eventInfo.location }}</span></p>
      </header>

      <div class="panel form-panel" :class="{ 'no-hero': !showHeader }">
        <div class="form-head">
          <h2>{{ form?.name }}<span v-if="eventInfo?.name && !embedded"> Registration</span></h2>
          <p>Fields marked <em>*</em> are required.</p>
        </div>

        <p v-if="errors._top" class="err top-err">{{ errors._top }}</p>

        <form novalidate @submit.prevent="submit">
          <!-- honeypot -->
          <input v-model="hp" type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

          <div class="grid">
            <template v-for="f in fields" :key="f.key">
              <!-- section break -->
              <div v-if="f.type === 'section_break'" class="w100 section">
                <h3>{{ f.label || 'Section' }}</h3>
                <p v-if="f.help_text">{{ f.help_text }}</p>
              </div>

              <!-- reCAPTCHA-style check -->
              <div v-else-if="f.type === 'recaptcha'" class="w100 captcha" :class="{ bad: errors._captcha }">
                <label class="check-row">
                  <input v-model="robot" type="checkbox">
                  <span>I'm not a robot</span>
                </label>
                <span class="captcha-brand">protected form</span>
              </div>

              <div v-else class="field" :class="(f.meta?.width === 50 && !embedded) ? 'w50' : 'w100'">
                <label :for="`fld-${f.key}`">
                  {{ f.label || f.key }} <em v-if="f.required">*</em>
                </label>

                <!-- choice groups -->
                <div v-if="f.type === 'radio'" class="choices">
                  <label v-for="o in f.options" :key="o.value || o.label" class="check-row">
                    <input v-model="values[f.key]" type="radio" :name="f.key" :value="o.value ?? o.label">
                    <span>{{ o.label }}</span>
                  </label>
                </div>

                <div v-else-if="ARRAY_TYPES.includes(f.type) && f.options.length" class="choices">
                  <label v-for="o in f.options" :key="o.value || o.label" class="check-row">
                    <input type="checkbox" :checked="(values[f.key] || []).includes(o.value ?? o.label)" @change="toggleArrayValue(f.key, (o.value ?? o.label) as string)">
                    <span>{{ o.label }}</span>
                  </label>
                </div>

                <label v-else-if="f.type === 'checkbox'" class="check-row">
                  <input type="checkbox" :checked="(values[f.key] || []).length > 0" @change="values[f.key] = (values[f.key] || []).length ? [] : ['yes']">
                  <span>{{ f.help_text || 'Yes' }}</span>
                </label>

                <select v-else-if="f.type === 'select'" :id="`fld-${f.key}`" v-model="values[f.key]">
                  <option value="" disabled>{{ f.meta?.placeholder || 'Select…' }}</option>
                  <option v-for="o in f.options" :key="o.value || o.label" :value="o.value ?? o.label">{{ o.label }}</option>
                </select>

                <textarea
                  v-else-if="f.type === 'textarea'" :id="`fld-${f.key}`" v-model="values[f.key]"
                  rows="4" :placeholder="f.meta?.placeholder || ''"
                />

                <!-- rating stars -->
                <div v-else-if="f.type === 'rating'" class="stars">
                  <button
                    v-for="n in Number(f.validation?.max || 5)" :key="n" type="button"
                    class="star" :class="{ on: Number(values[f.key]) >= n }"
                    :aria-label="`${n} star${n > 1 ? 's' : ''}`"
                    @click="values[f.key] = String(n)"
                  >★</button>
                </div>

                <input
                  v-else
                  :id="`fld-${f.key}`"
                  v-model="values[f.key]"
                  :type="f.type === 'email' ? 'email' : f.type === 'phone' ? 'tel' : f.type === 'number' ? 'number' : f.type === 'date' ? 'date' : f.type === 'link' || f.type === 'file' ? 'url' : 'text'"
                  :placeholder="f.meta?.placeholder || (f.type === 'file' ? 'Paste a link to your file (Drive, Dropbox…)' : '')"
                >

                <p v-if="f.help_text && f.type !== 'checkbox'" class="hint">{{ f.help_text }}</p>
                <p v-if="errors[f.key]" class="err">{{ errors[f.key] }}</p>
              </div>
            </template>
          </div>

          <p v-if="errors._captcha" class="err">{{ errors._captcha }}</p>

          <button type="submit" class="submit" :disabled="submitting">
            {{ submitting ? 'Submitting…' : 'Submit' }}
          </button>
          <p class="powered">Powered by <strong>EXPOUSE</strong></p>
        </form>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* The page background is set inline from the organizer's design settings;
   this is only the fallback before that resolves. */
.page { min-height: 100vh; background: #f1f3f9; padding-bottom: 48px; }
.page.embedded { background: transparent !important; padding-bottom: 12px; }

.center { min-height: 70vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
.muted-block { color: #64748b; font-size: .95rem; }

.hero {
  background: linear-gradient(135deg, #1e293b, #0f172a); background-size: cover; background-position: center;
  color: #fff; text-align: center; padding: 52px 20px 60px;
}
.hero-logo { height: 46px; margin-bottom: 14px; object-fit: contain; }
.hero h1 { margin: 0; font-size: 1.7rem; font-weight: 800; }
.hero-sub { margin: 8px 0 0; opacity: .85; font-size: .95rem; }

.panel {
  background: #fff; border-radius: 18px; box-shadow: 0 10px 34px rgba(15, 23, 42, .1);
  width: 100%; max-width: 680px; margin: 0 auto; padding: 28px;
}
.form-panel { margin-top: -34px; position: relative; }
/* Without the event hero above it, the card needs its own breathing room
   rather than the overlap the hero was built for. */
.form-panel.no-hero { margin-top: 40px; }
.embedded .form-panel { margin-top: 0; box-shadow: none; border: 1px solid #e2e8f0; max-width: none; }

/* Frosted glass — lets a background image read through the card. */
.glass .panel {
  background: rgba(255, 255, 255, .82);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, .6);
}
.page.embedded.glass .panel { background: rgba(255, 255, 255, .82); }
.narrow { max-width: 460px; text-align: center; }

.done-icon { width: 54px; height: 54px; border-radius: 50%; background: var(--bp); display: grid; place-items: center; margin: 4px auto 16px; }
.miss-title { margin: 0 0 8px; font-size: 1.3rem; font-weight: 800; color: #1e293b; }
.miss-text { margin: 0; color: #64748b; font-size: .93rem; line-height: 1.6; }

.form-head h2 { margin: 0 0 4px; font-size: 1.18rem; font-weight: 800; color: #1e293b; }
.form-head p { margin: 0 0 18px; color: #94a3b8; font-size: .82rem; }
.form-head em, .field em { color: #dc2626; font-style: normal; }

.hp { position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; }

.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; }
.w100 { grid-column: 1 / -1; }
.w50 { grid-column: span 1; }
@media (max-width: 620px) { .w50 { grid-column: 1 / -1; } }

.section { border-top: 1px solid #e2e8f0; padding-top: 16px; margin-top: 6px; }
.section h3 { margin: 0; font-size: 1rem; font-weight: 800; color: #1e293b; }
.section p { margin: 4px 0 0; color: #64748b; font-size: .84rem; }

.field label:first-child { display: block; font-size: .84rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
.field input:not([type='checkbox']):not([type='radio']), .field select, .field textarea {
  width: 100%; border: 1px solid #d7dae1; border-radius: 10px; padding: 10px 12px;
  font: inherit; font-size: .9rem; color: #1e293b; outline: none; background: #fff; box-sizing: border-box;
}
.field input:focus, .field select:focus, .field textarea:focus { border-color: var(--bp); box-shadow: 0 0 0 3px color-mix(in srgb, var(--bp) 14%, transparent); }

.choices { display: flex; flex-direction: column; gap: 8px; padding: 2px 0; }
.check-row { display: flex; align-items: center; gap: 9px; cursor: pointer; font-size: .9rem; color: #1e293b; font-weight: 500; }
.check-row input { width: 16px; height: 16px; margin: 0; accent-color: var(--bp); }

.stars { display: flex; gap: 4px; }
.star { border: none; background: none; font-size: 1.55rem; line-height: 1; color: #d7dae1; cursor: pointer; padding: 2px; }
.star.on { color: #f59e0b; }

.captcha {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  border: 1px solid #d7dae1; border-radius: 10px; padding: 14px 16px; background: #f8fafc;
}
.captcha.bad { border-color: #dc2626; }
.captcha-brand { font-size: .68rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; }

.hint { margin: 5px 0 0; font-size: .76rem; color: #94a3b8; }
.err { margin: 5px 0 0; font-size: .78rem; color: #dc2626; font-weight: 600; }
.top-err { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 10px 12px; }

.submit {
  margin-top: 20px; width: 100%; border: none; border-radius: 12px; background: var(--bp); color: #fff;
  font: inherit; font-size: .95rem; font-weight: 700; padding: 13px 18px; cursor: pointer; transition: filter .15s;
}
.submit:hover { filter: brightness(.92); }
.submit:disabled { opacity: .6; cursor: default; }

.powered { text-align: center; margin: 14px 0 0; font-size: .7rem; color: #b6bdca; text-transform: uppercase; letter-spacing: .08em; }
</style>
