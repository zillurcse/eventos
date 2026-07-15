<script setup lang="ts">
/**
 * Profile-completion onboarding (Settings › Onboarding).
 *
 * When the organizer has onboarding on, a first-time attendee is asked to fill
 * in the profile the networking features live on — job title, company, a short
 * bio, interests — before they get to Reception. The server owns the "should we
 * ask?" decision (`meta.needs_onboarding`): it knows the organizer's setting and
 * what counts as complete, and the client just renders the answer.
 *
 * Skippable on purpose. A hard wall in front of an event someone is already at
 * loses them; a gentle nudge that fills the delegate directory does not. Either
 * way it is marked done so we don't ask twice.
 */
const site = useSiteStore()
const auth = useAuthStore()
const api = useApi()

const open = ref(false)
const saving = ref(false)
const loaded = ref(false)

const form = reactive({
  job_title: '',
  company: '',
  bio: '',
  interests: [] as string[],
})
const interestInput = ref('')

const eventUuid = computed(() => site.event?.uuid)

/** Ask the server whether this attendee still needs onboarding. */
async function check() {
  if (loaded.value || !auth.isAuthed || !eventUuid.value) return
  if (!site.site?.login?.onboarding) return // organizer has it off

  loaded.value = true
  try {
    const r = await api<any>(`/events/${eventUuid.value}/profile`)
    if (r.meta?.needs_onboarding) {
      // Prefill anything registration already captured.
      form.job_title = r.data?.job_title || ''
      form.company = r.data?.company || ''
      form.bio = r.data?.bio || ''
      open.value = true
    }
  } catch { /* a failed check just means no onboarding this time */ }
}

function addInterest() {
  const t = interestInput.value.trim().replace(/,$/, '')
  if (t && !form.interests.includes(t) && form.interests.length < 12) form.interests.push(t)
  interestInput.value = ''
}
function removeInterest(i: number) { form.interests.splice(i, 1) }

async function save(complete: boolean) {
  if (saving.value) return
  saving.value = true
  try {
    await api(`/events/${eventUuid.value}/profile`, {
      method: 'PUT',
      body: {
        job_title: form.job_title.trim() || undefined,
        company: form.company.trim() || undefined,
        bio: form.bio.trim() || undefined,
        interests: form.interests,
        // Both "Save" and "Skip" mark it done — either way, don't ask again.
        complete_onboarding: true,
      },
    })
    open.value = false
    if (complete) { /* stayed to fill it in */ }
  } catch { /* leave the modal open so they can retry */ } finally {
    saving.value = false
  }
}

// Auth and the site payload land at different times; re-check when either does.
watch(() => [auth.user, site.site], check, { immediate: true })
onMounted(check)
</script>

<template>
  <div v-if="open" class="overlay">
    <div class="modal" role="dialog" aria-modal="true" aria-label="Complete your profile">
      <div class="head">
        <h2>Welcome{{ auth.user?.name ? `, ${auth.user.name.split(' ')[0]}` : '' }} 👋</h2>
        <p>Tell other attendees a little about you — it's how people find and connect with you here.</p>
      </div>

      <div class="body">
        <label class="field">
          <span>Job title</span>
          <input v-model="form.job_title" type="text" maxlength="150" placeholder="e.g. Product Manager">
        </label>

        <label class="field">
          <span>Company</span>
          <input v-model="form.company" type="text" maxlength="150" placeholder="e.g. Acme Inc.">
        </label>

        <label class="field">
          <span>About you <em>(optional)</em></span>
          <textarea v-model="form.bio" rows="3" maxlength="2000" placeholder="A sentence or two about what you do" />
        </label>

        <div class="field">
          <span>Interests <em>(optional)</em></span>
          <div class="tags">
            <span v-for="(t, i) in form.interests" :key="i" class="tag">
              {{ t }}<button type="button" @click="removeInterest(i)">×</button>
            </span>
          </div>
          <input
            v-model="interestInput" type="text" placeholder="Type an interest and press Enter"
            @keydown.enter.prevent="addInterest" @keydown="e => e.key === ',' && (e.preventDefault(), addInterest())"
          >
        </div>
      </div>

      <div class="foot">
        <button type="button" class="skip" :disabled="saving" @click="save(false)">Skip for now</button>
        <button type="button" class="save" :disabled="saving" @click="save(true)">
          {{ saving ? 'Saving…' : 'Save & continue' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.6); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 95; }
.modal { background: #fff; border-radius: 18px; width: 100%; max-width: 520px; overflow: hidden; box-shadow: 0 24px 60px rgba(15,23,42,.34); }

.head { padding: 24px 24px 8px; }
.head h2 { margin: 0 0 6px; font-size: 1.25rem; font-weight: 800; color: #1e293b; }
.head p { margin: 0; color: #64748b; font-size: .9rem; line-height: 1.5; }

.body { padding: 12px 24px; display: flex; flex-direction: column; gap: 14px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { font-size: .82rem; font-weight: 600; color: #334155; }
.field em { color: #94a3b8; font-weight: 400; font-style: normal; }
.field input, .field textarea {
  width: 100%; border: 1px solid #d7dae1; border-radius: 10px; padding: 10px 12px; font: inherit; font-size: .9rem; color: #1e293b; outline: none;
}
.field input:focus, .field textarea:focus { border-color: var(--brand-primary); }

.tags { display: flex; flex-wrap: wrap; gap: 6px; }
.tag { display: inline-flex; align-items: center; gap: 5px; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); border-radius: 999px; padding: 4px 10px; font-size: .8rem; font-weight: 600; }
.tag button { border: none; background: none; color: inherit; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0; }

.foot { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px 22px; }
.skip { border: none; background: none; color: #64748b; font: inherit; font-size: .88rem; cursor: pointer; }
.skip:hover { color: #334155; }
.save { border: none; border-radius: 11px; background: var(--brand-primary); color: #fff; font: inherit; font-size: .9rem; font-weight: 700; padding: 11px 20px; cursor: pointer; }
.save:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.save:disabled, .skip:disabled { opacity: .6; cursor: default; }
</style>
