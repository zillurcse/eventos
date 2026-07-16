<script setup lang="ts">
const profile = useProfileStore()

const saving = ref(false)
const savedFlash = ref(false)
const tags = ref<string[]>([])
const input = ref('')

watch(() => profile.data, (p) => { tags.value = [...(p?.interests || [])] }, { immediate: true })

function addTag() {
  const t = input.value.trim().replace(/,$/, '')
  if (t && !tags.value.includes(t) && tags.value.length < 12) tags.value.push(t)
  input.value = ''
}
function removeTag(i: number) { tags.value.splice(i, 1) }

async function save() {
  if (saving.value) return
  saving.value = true
  try {
    await profile.save({ interests: tags.value })
    savedFlash.value = true
    setTimeout(() => { savedFlash.value = false }, 2500)
  } finally {
    saving.value = false
  }
}

function cancel() { tags.value = [...(profile.data?.interests || [])] }
</script>

<template>
  <div class="tab">
    <p class="hint">What are you interested in at this event? This helps other attendees find and connect with you.</p>

    <div class="tags-box">
      <span v-for="(t, i) in tags" :key="i" class="tag">{{ t }}<button type="button" @click="removeTag(i)">×</button></span>
      <input
        v-model="input" type="text" placeholder="Type an interest and press Enter"
        @keydown.enter.prevent="addTag" @keydown="e => e.key === ',' && (e.preventDefault(), addTag())"
      >
    </div>

    <div class="foot">
      <button type="button" class="btn primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save' }}</button>
      <button type="button" class="btn text" :disabled="saving" @click="cancel">Cancel</button>
      <span v-if="savedFlash" class="saved">Saved</span>
    </div>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 18px; max-width: 720px; }
.hint { margin: 0; color: #64748b; font-size: .88rem; line-height: 1.5; }

.tags-box { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; border: 1px solid #d7dae1; border-radius: 12px; padding: 10px 12px; }
.tags-box input { flex: 1; min-width: 160px; border: none; outline: none; font: inherit; font-size: .9rem; padding: 6px 4px; }
.tag { display: inline-flex; align-items: center; gap: 6px; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); border-radius: 999px; padding: 6px 12px; font-size: .84rem; font-weight: 600; }
.tag button { border: none; background: none; color: inherit; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0; }

.foot { display: flex; align-items: center; gap: 16px; padding-top: 8px; border-top: 1px solid #f1f2f6; }
.btn { border: none; border-radius: 10px; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; padding: 11px 22px; }
.btn.primary { background: var(--brand-primary); color: #fff; }
.btn.primary:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn.text { background: none; color: var(--brand-primary); padding: 11px 4px; }
.btn:disabled { opacity: .6; cursor: default; }
.saved { color: #16a34a; font-size: .85rem; font-weight: 600; }
</style>
