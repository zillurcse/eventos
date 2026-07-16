<script setup lang="ts">
import { toast } from 'vue-sonner'

const profile = useProfileStore()

const saving = ref(false)
const lookingFor = ref<string[]>([])
const offering = ref<string[]>([])
const lookingInput = ref('')
const offeringInput = ref('')

watch(() => profile.data, (p) => {
  lookingFor.value = [...(p?.looking_for || [])]
  offering.value = [...(p?.offering || [])]
}, { immediate: true })

function addTag(list: typeof lookingFor, input: typeof lookingInput) {
  const t = input.value.trim().replace(/,$/, '')
  if (t && !list.value.includes(t) && list.value.length < 12) list.value.push(t)
  input.value = ''
}
function removeTag(list: typeof lookingFor, i: number) { list.value.splice(i, 1) }

async function save() {
  if (saving.value) return
  saving.value = true
  try {
    await profile.save({ looking_for: lookingFor.value, offering: offering.value })
    toast.success('Saved')
  } catch {
    toast.error('Could not save your changes.')
  } finally {
    saving.value = false
  }
}

function cancel() {
  lookingFor.value = [...(profile.data?.looking_for || [])]
  offering.value = [...(profile.data?.offering || [])]
}
</script>

<template>
  <div class="tab">
    <div class="section">
      <p class="label">I'm looking for</p>
      <div class="tags-box">
        <span v-for="(t, i) in lookingFor" :key="i" class="tag">{{ t }}<button type="button" @click="removeTag(lookingFor, i)">×</button></span>
        <input
          v-model="lookingInput" type="text" placeholder="e.g. Investment, Partnership, Job opportunities"
          @keydown.enter.prevent="addTag(lookingFor, lookingInput)"
          @keydown="e => e.key === ',' && (e.preventDefault(), addTag(lookingFor, lookingInput))"
        >
      </div>
    </div>

    <div class="section">
      <p class="label">I'm offering</p>
      <div class="tags-box">
        <span v-for="(t, i) in offering" :key="i" class="tag">{{ t }}<button type="button" @click="removeTag(offering, i)">×</button></span>
        <input
          v-model="offeringInput" type="text" placeholder="e.g. Mentorship, Product demo, Services"
          @keydown.enter.prevent="addTag(offering, offeringInput)"
          @keydown="e => e.key === ',' && (e.preventDefault(), addTag(offering, offeringInput))"
        >
      </div>
    </div>

    <div class="foot">
      <button type="button" class="btn primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save' }}</button>
      <button type="button" class="btn text" :disabled="saving" @click="cancel">Cancel</button>
    </div>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 22px; max-width: 720px; }
.section { display: flex; flex-direction: column; gap: 8px; }
.label { margin: 0; font-size: .88rem; font-weight: 700; color: #334155; }

.tags-box { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; border: 1px solid #d7dae1; border-radius: 12px; padding: 10px 12px; }
.tags-box input { flex: 1; min-width: 200px; border: none; outline: none; font: inherit; font-size: .9rem; padding: 6px 4px; }
.tag { display: inline-flex; align-items: center; gap: 6px; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); border-radius: 999px; padding: 6px 12px; font-size: .84rem; font-weight: 600; }
.tag button { border: none; background: none; color: inherit; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0; }

.foot { display: flex; align-items: center; gap: 16px; padding-top: 8px; border-top: 1px solid #f1f2f6; }
.btn { border: none; border-radius: 10px; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; padding: 11px 22px; }
.btn.primary { background: var(--brand-primary); color: #fff; }
.btn.primary:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn.text { background: none; color: var(--brand-primary); padding: 11px 4px; }
.btn:disabled { opacity: .6; cursor: default; }
</style>
