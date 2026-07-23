<script setup lang="ts">
import { toast } from 'vue-sonner'

const profile = useProfileStore()
const feed = useFeedStore()

const saving = ref(false)
const lookingFor = ref<string[]>([])
const offering = ref<string[]>([])

// Option lists come from the tags people are actually using on the feed
// (looking_for / offering posts). Loaded once when the tab mounts.
const lookingOpts = ref<string[]>([])
const offeringOpts = ref<string[]>([])

watch(() => profile.data, (p) => {
  lookingFor.value = [...(p?.looking_for || [])]
  offering.value = [...(p?.offering || [])]
}, { immediate: true })

// The user's own picks always belong in the list (checked), even a custom tag
// the feed hasn't seen — otherwise a saved value would vanish from the dropdown.
const lookingChoices = computed(() => Array.from(new Set([...lookingOpts.value, ...lookingFor.value])))
const offeringChoices = computed(() => Array.from(new Set([...offeringOpts.value, ...offering.value])))

onMounted(async () => {
  try {
    const t = await feed.fetchNetworkingTags()
    lookingOpts.value = t.looking_for
    offeringOpts.value = t.offering
  } catch {
    // Options are a convenience — typing a new tag still works without them.
  }
})

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
    <p class="hint">Pick what you're looking for and what you can offer — drawn from what the community is talking about on the feed. This helps the right people find you.</p>

    <div class="section">
      <p class="label">Looking for</p>
      <ProfileMultiTagSelect v-model="lookingFor" :options="lookingChoices" placeholder="Select Options" />
    </div>

    <div class="section">
      <p class="label">Offering</p>
      <ProfileMultiTagSelect v-model="offering" :options="offeringChoices" placeholder="Select Options" />
    </div>

    <div class="foot">
      <button type="button" class="btn primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save' }}</button>
      <button type="button" class="btn text" :disabled="saving" @click="cancel">Cancel</button>
    </div>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 22px; max-width: 720px; }
.hint { margin: 0; color: #64748b; font-size: .88rem; line-height: 1.5; }
.section { display: flex; flex-direction: column; gap: 8px; }
.label { margin: 0; font-size: .88rem; font-weight: 700; color: #334155; }

.foot { display: flex; align-items: center; gap: 16px; padding-top: 8px; border-top: 1px solid #f1f2f6; }
.btn { border: none; border-radius: 10px; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; padding: 11px 22px; }
.btn.primary { background: var(--brand-primary); color: #fff; }
.btn.primary:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn.text { background: none; color: var(--brand-primary); padding: 11px 4px; }
.btn:disabled { opacity: .6; cursor: default; }
</style>
