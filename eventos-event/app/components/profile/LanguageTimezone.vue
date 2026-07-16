<script setup lang="ts">
import { toast } from 'vue-sonner'
import { LANGUAGES } from '~/utils/languages'
import { TIMEZONES } from '~/utils/timezones'

const profile = useProfileStore()

const form = reactive({ language: '', timezone: '' })

function fillFromProfile() {
  const p = profile.data
  if (!p) return
  form.language = p.language || ''
  form.timezone = p.timezone || ''
}

watch(() => profile.data, fillFromProfile, { immediate: true })

const saving = ref(false)

async function save() {
  if (saving.value) return
  saving.value = true
  try {
    await profile.save({ language: form.language || undefined, timezone: form.timezone || undefined })
    toast.success('Preferences saved')
  } catch {
    toast.error('Could not save your preferences.')
  } finally {
    saving.value = false
  }
}

function cancel() { fillFromProfile() }
</script>

<template>
  <div class="tab">
    <label class="field">
      <span>Language</span>
      <ProfileSearchSelect v-model="form.language" :options="LANGUAGES" title="Select Language" />
    </label>

    <label class="field">
      <span>Time Zone</span>
      <ProfileSearchSelect v-model="form.timezone" :options="TIMEZONES" title="Select Time Zone" />
    </label>

    <div class="foot">
      <button type="button" class="btn primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save' }}</button>
      <button type="button" class="btn text" :disabled="saving" @click="cancel">Cancel</button>
    </div>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 20px; max-width: 460px; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field > span { font-size: .84rem; font-weight: 600; color: #334155; }

.foot { display: flex; align-items: center; gap: 16px; padding-top: 18px; border-top: 1px solid #f1f2f6; margin-top: 4px; }
.btn { border: none; border-radius: 10px; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; padding: 11px 22px; }
.btn.primary { background: var(--brand-primary); color: #fff; }
.btn.primary:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn.text { background: none; color: var(--brand-primary); padding: 11px 4px; }
.btn:disabled { opacity: .6; cursor: default; }
</style>
