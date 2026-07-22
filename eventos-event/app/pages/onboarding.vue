<script setup lang="ts">
/**
 * Post-login onboarding — a full PAGE, not a popup (Settings › Onboarding).
 *
 * First-time attendees land here (routed by EventOnboardingGate) to complete
 * the profile the networking features live on. The form is the organizer's
 * attendee profile form (admin › Event Settings › Profile): every field whose
 * `onboarding` surface is on renders here, honouring the 50% / 100% widths
 * chosen in the builder. The basics (job title, company, bio, interests) are
 * native so the page works even before any profile form is published.
 *
 * Skippable on purpose — a hard wall in front of an event someone is already
 * at loses them. Either path marks onboarding done so we never ask twice.
 */
definePageMeta({ layout: false, middleware: 'auth' })

const site = useSiteStore()
const auth = useAuthStore()
const api = useApi()

const state = ref<'loading' | 'ready'>('loading')
const saving = ref(false)

const form = reactive({
  job_title: '',
  company: '',
  bio: '',
  interests: [] as string[],
})
const interestInput = ref('')

// Organizer-defined onboarding fields, keyed by field key.
const custom = reactive<Record<string, any>>({})
const errors = reactive<Record<string, string>>({})
const customFields = computed(() => site.onboardingFields)
const multi = (t: string) => ['multiselect', 'checkbox'].includes(t)

const eventUuid = computed(() => site.event?.uuid)
const firstName = computed(() => auth.user?.name?.split(' ')[0] || '')

onMounted(async () => {
  // The gate normally routes here only when needed, but a direct visit (or a
  // refresh after finishing) should bounce straight to Reception.
  try {
    const r = await api<any>(`/events/${eventUuid.value}/profile`)
    if (!r.meta?.needs_onboarding) return navigateTo('/reception', { replace: true })
    form.job_title = r.data?.job_title || ''
    form.company = r.data?.company || ''
    form.bio = r.data?.bio || ''
    for (const f of customFields.value) custom[f.key] = multi(f.type) ? [] : ''
    state.value = 'ready'
  } catch {
    navigateTo('/reception', { replace: true })
  }
})

function addInterest() {
  const t = interestInput.value.trim().replace(/,$/, '')
  if (t && !form.interests.includes(t) && form.interests.length < 12) form.interests.push(t)
  interestInput.value = ''
}
function removeInterest(i: number) { form.interests.splice(i, 1) }

function toggleChoice(key: string, v: string) {
  const arr: string[] = custom[key] || (custom[key] = [])
  const i = arr.indexOf(v)
  i >= 0 ? arr.splice(i, 1) : arr.push(v)
}

function validate(): boolean {
  Object.keys(errors).forEach(k => delete errors[k])
  for (const f of customFields.value) {
    if (!f.required) continue
    const v = custom[f.key]
    const empty = Array.isArray(v) ? !v.length : String(v ?? '').trim() === ''
    if (empty) errors[f.key] = `${f.label || 'This field'} is required.`
  }
  return !Object.keys(errors).length
}

async function save(complete: boolean) {
  if (saving.value) return
  if (complete && !validate()) return

  saving.value = true
  try {
    const filled: Record<string, any> = {}
    if (complete) {
      for (const [k, v] of Object.entries(custom)) {
        if (Array.isArray(v) ? v.length : String(v ?? '').trim() !== '') filled[k] = v
      }
    }

    await api(`/events/${eventUuid.value}/profile`, {
      method: 'PUT',
      body: {
        job_title: complete ? (form.job_title.trim() || undefined) : undefined,
        company: complete ? (form.company.trim() || undefined) : undefined,
        bio: complete ? (form.bio.trim() || undefined) : undefined,
        interests: complete ? form.interests : undefined,
        custom: Object.keys(filled).length ? filled : undefined,
        // Both "Save" and "Skip" mark it done — either way, don't ask again.
        complete_onboarding: true,
      },
    })
    navigateTo('/reception', { replace: true })
  } catch { /* stay on the page so they can retry */ } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="topband" />

    <div class="wrap">
      <header class="head">
        <img v-if="site.logoUrl" :src="site.logoUrl" :alt="site.name" class="logo">
        <div v-else class="logo-ph">{{ site.name }}</div>
        <h1>Welcome{{ firstName ? `, ${firstName}` : '' }} 👋</h1>
        <p>
          Set up your profile for <strong>{{ site.name }}</strong> — it's how other
          attendees find and connect with you.
        </p>
      </header>

      <div v-if="state === 'loading'" class="panel loading">Loading your profile…</div>

      <form v-else class="panel" novalidate @submit.prevent="save(true)">
        <div class="grid">
          <label class="field w50">
            <span>Job title</span>
            <input v-model="form.job_title" type="text" maxlength="150" placeholder="e.g. Product Manager">
          </label>

          <label class="field w50">
            <span>Company</span>
            <input v-model="form.company" type="text" maxlength="150" placeholder="e.g. Acme Inc.">
          </label>

          <label class="field w100">
            <span>About you <em>(optional)</em></span>
            <textarea v-model="form.bio" rows="3" maxlength="2000" placeholder="A sentence or two about what you do" />
          </label>

          <div class="field w100">
            <span>Interests <em>(optional)</em></span>
            <div v-if="form.interests.length" class="tags">
              <span v-for="(t, i) in form.interests" :key="i" class="tag">
                {{ t }}<button type="button" @click="removeInterest(i)">×</button>
              </span>
            </div>
            <input
              v-model="interestInput" type="text" placeholder="Type an interest and press Enter"
              @keydown.enter.prevent="addInterest" @keydown="e => e.key === ',' && (e.preventDefault(), addInterest())"
            >
          </div>

          <!-- Organizer-defined onboarding fields (Event Settings › Profile),
               laid out with the widths chosen in the builder. -->
          <template v-for="f in customFields" :key="f.key">
            <div v-if="f.type === 'radio' || (multi(f.type) && f.options.length)" class="field" :class="f.meta?.width === 50 ? 'w50' : 'w100'">
              <span>{{ f.label || f.key }} <em v-if="!f.required">(optional)</em></span>
              <div class="choices">
                <label v-for="o in f.options" :key="o.value ?? o.label" class="choice">
                  <input
                    v-if="f.type === 'radio'"
                    v-model="custom[f.key]" type="radio" :name="`cf-${f.key}`" :value="o.value ?? o.label"
                  >
                  <input
                    v-else
                    type="checkbox" :checked="(custom[f.key] || []).includes(o.value ?? o.label)"
                    @change="toggleChoice(f.key, (o.value ?? o.label) as string)"
                  >
                  <span class="choice-label">{{ o.label }}</span>
                </label>
              </div>
              <small v-if="errors[f.key]" class="err">{{ errors[f.key] }}</small>
            </div>

            <label v-else class="field" :class="f.meta?.width === 50 ? 'w50' : 'w100'">
              <span>{{ f.label || f.key }} <em v-if="!f.required">(optional)</em></span>
              <select v-if="f.type === 'select'" v-model="custom[f.key]">
                <option value="" disabled>{{ f.meta?.placeholder || 'Select…' }}</option>
                <option v-for="o in f.options" :key="o.value ?? o.label" :value="o.value ?? o.label">{{ o.label }}</option>
              </select>
              <textarea v-else-if="f.type === 'textarea'" v-model="custom[f.key]" rows="3" :placeholder="f.meta?.placeholder || ''" />
              <input
                v-else
                v-model="custom[f.key]"
                :type="f.type === 'email' ? 'email' : f.type === 'phone' ? 'tel' : f.type === 'number' || f.type === 'rating' ? 'number' : f.type === 'date' ? 'date' : f.type === 'link' ? 'url' : 'text'"
                :placeholder="f.meta?.placeholder || ''"
              >
              <small v-if="f.help_text" class="help">{{ f.help_text }}</small>
              <small v-if="errors[f.key]" class="err">{{ errors[f.key] }}</small>
            </label>
          </template>
        </div>

        <div class="foot">
          <button type="button" class="skip" :disabled="saving" @click="save(false)">Skip for now</button>
          <button type="submit" class="save" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save & continue' }}
          </button>
        </div>
      </form>

      <p class="powered">Powered by <strong>{{ site.poweredBy }}</strong></p>
    </div>
  </div>
</template>

<style scoped>
.page { min-height: 100vh; background: #f1f3f9; }
.topband {
  height: 220px;
  background: linear-gradient(135deg, var(--brand-primary, #6352e7), color-mix(in srgb, var(--brand-primary, #6352e7) 55%, #0f172a));
}

.wrap { max-width: 760px; margin: -150px auto 0; padding: 0 20px 48px; }

.head { text-align: center; color: #fff; margin-bottom: 22px; }
.logo { height: 44px; object-fit: contain; margin-bottom: 12px; }
.logo-ph { font-weight: 800; font-size: 1.05rem; letter-spacing: .02em; margin-bottom: 12px; opacity: .95; }
.head h1 { margin: 0 0 6px; font-size: 1.55rem; font-weight: 800; }
.head p { margin: 0 auto; max-width: 460px; font-size: .93rem; line-height: 1.55; opacity: .88; }

.panel { background: #fff; border-radius: 18px; box-shadow: 0 14px 44px rgba(15, 23, 42, .14); padding: 28px; }
.panel.loading { text-align: center; color: #64748b; font-size: .93rem; padding: 48px; }

.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 18px; }
.w100 { grid-column: 1 / -1; }
.w50 { grid-column: span 1; }
@media (max-width: 640px) { .w50 { grid-column: 1 / -1; } }

.field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.field > span { font-size: .83rem; font-weight: 600; color: #334155; }
.field em { color: #94a3b8; font-weight: 400; font-style: normal; }
.field input:not([type='checkbox']):not([type='radio']), .field select, .field textarea {
  width: 100%; border: 1px solid #d7dae1; border-radius: 10px; padding: 10px 12px;
  font: inherit; font-size: .9rem; color: #1e293b; outline: none; background: #fff; box-sizing: border-box;
}
.field input:focus, .field select:focus, .field textarea:focus {
  border-color: var(--brand-primary, #6352e7);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-primary, #6352e7) 13%, transparent);
}
.help { color: #94a3b8; font-size: .74rem; }
.err { color: #dc2626; font-size: .76rem; font-weight: 600; }

.tags { display: flex; flex-wrap: wrap; gap: 6px; }
.tag {
  display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 4px 10px;
  font-size: .8rem; font-weight: 600;
  background: color-mix(in srgb, var(--brand-primary, #6352e7) 10%, #fff);
  color: var(--brand-primary, #6352e7);
}
.tag button { border: none; background: none; color: inherit; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0; }

.choices { display: flex; flex-direction: column; gap: 8px; padding: 2px 0; }
.choice { display: flex; align-items: center; gap: 9px; cursor: pointer; }
.choice input { width: 16px; height: 16px; margin: 0; accent-color: var(--brand-primary, #6352e7); }
.choice-label { font-size: .9rem; color: #1e293b; font-weight: 500; }

.foot { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; }
.skip { border: none; background: none; color: #64748b; font: inherit; font-size: .88rem; cursor: pointer; }
.skip:hover { color: #334155; }
.save {
  border: none; border-radius: 12px; background: var(--brand-primary, #6352e7); color: #fff;
  font: inherit; font-size: .93rem; font-weight: 700; padding: 12px 22px; cursor: pointer; transition: filter .15s;
}
.save:hover { filter: brightness(.92); }
.save:disabled, .skip:disabled { opacity: .6; cursor: default; }

.powered { text-align: center; margin: 18px 0 0; font-size: .7rem; color: #b6bdca; text-transform: uppercase; letter-spacing: .08em; }
</style>
