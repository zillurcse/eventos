<script setup lang="ts">
import { toast } from 'vue-sonner'

const store = useNotificationPreferencesStore()
store.fetch()

const EMAIL_ROWS = [
  { category: 'meetings', label: 'Meetings' },
  { category: 'messages', label: 'Messages' },
  { category: 'profile_views', label: 'Profile Views' },
  { category: 'mentions', label: 'Mention Notifications' },
] as const

const PUSH_ROWS = [
  { category: 'admin_post', label: 'Admin Post Conducted' },
  { category: 'new_activity', label: 'New Activity on My Post' },
  { category: 'organiser', label: 'Organiser Notifications' },
  { category: 'meeting_status', label: 'Meeting Status Notifications' },
  { category: 'messages', label: 'Message Notifications' },
  { category: 'profile_views', label: 'Profile Views' },
  { category: 'session_live', label: 'Session Go Live' },
  { category: 'mentions', label: 'Mention Notifications' },
] as const

// Local edit buffer: category -> {email, in_app}, seeded from the store and
// re-seeded whenever a fresh fetch lands so Cancel has something to revert to.
const draft = ref<Record<string, { email: boolean, in_app: boolean }>>({})

function reset() {
  const next: Record<string, { email: boolean, in_app: boolean }> = {}
  for (const row of [...EMAIL_ROWS, ...PUSH_ROWS]) {
    const saved = store.byCategory(row.category)
    next[row.category] = { email: saved?.email ?? false, in_app: saved?.in_app ?? false }
  }
  draft.value = next
}

watch(() => store.prefs, reset, { immediate: true })

const saving = ref(false)

async function save() {
  if (saving.value) return
  saving.value = true
  try {
    await store.save(Object.entries(draft.value).map(([category, v]) => ({ category, ...v })))
    toast.success('Notification settings saved')
  } catch {
    toast.error('Could not save your notification settings.')
  } finally {
    saving.value = false
  }
}

function cancel() { reset() }
</script>

<template>
  <div class="settings">
    <p v-if="store.loading && !store.loaded" class="loading">Loading…</p>

    <template v-else>
      <section class="group">
        <h2>Email</h2>
        <label v-for="row in EMAIL_ROWS" :key="`email-${row.category}`" class="row">
          <input v-model="draft[row.category]!.email" type="checkbox">
          <span class="box"><svg viewBox="0 0 24 24"><path d="M5 12l5 5L19 7" /></svg></span>
          {{ row.label }}
        </label>
      </section>

      <section class="group">
        <h2>Mobile &amp; Desktop</h2>
        <label v-for="row in PUSH_ROWS" :key="`push-${row.category}`" class="row">
          <input v-model="draft[row.category]!.in_app" type="checkbox">
          <span class="box"><svg viewBox="0 0 24 24"><path d="M5 12l5 5L19 7" /></svg></span>
          {{ row.label }}
        </label>
      </section>

      <div class="foot">
        <button type="button" class="btn primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save' }}</button>
        <button type="button" class="btn text" :disabled="saving" @click="cancel">Cancel</button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.settings { display: flex; flex-direction: column; gap: 8px; max-width: 640px; }
.loading { color: #94a3b8; font-size: .9rem; }

.group { padding: 18px 0; border-bottom: 1px solid #f1f2f6; }
.group:first-child { padding-top: 0; }
.group h2 { margin: 0 0 14px; font-size: .95rem; font-weight: 700; color: #1e293b; }

.row { display: flex; align-items: center; gap: 10px; padding: 9px 0; font-size: .9rem; color: #334155; cursor: pointer; user-select: none; }
.row input { position: absolute; opacity: 0; width: 1px; height: 1px; }

.box {
  flex: 0 0 auto; width: 19px; height: 19px; border-radius: 5px; border: 1.5px solid #cbd2db;
  display: flex; align-items: center; justify-content: center; transition: background .12s, border-color .12s;
}
.box svg { width: 13px; height: 13px; fill: none; stroke: #fff; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; opacity: 0; transform: scale(.6); transition: opacity .12s, transform .12s; }
.row input:checked + .box { background: var(--brand-primary); border-color: var(--brand-primary); }
.row input:checked + .box svg { opacity: 1; transform: scale(1); }
.row input:focus-visible + .box { outline: 2px solid color-mix(in srgb, var(--brand-primary) 40%, transparent); outline-offset: 2px; }

.foot { display: flex; align-items: center; gap: 16px; padding-top: 18px; }
.btn { border: none; border-radius: 10px; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; padding: 11px 22px; }
.btn.primary { background: var(--brand-primary); color: #fff; }
.btn.primary:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn.text { background: none; color: var(--brand-primary); padding: 11px 4px; }
.btn:disabled { opacity: .6; cursor: default; }
</style>
