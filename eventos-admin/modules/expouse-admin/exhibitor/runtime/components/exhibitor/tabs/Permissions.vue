<script setup lang="ts">
import {
  ALL_FEATURES,
  featureLabel,
  featureCountable,
  mergeFeatures,
  type FeatureLine,
} from '../../../utils/exhibitor'

const {
  entitlements, subSaving, subError, savePermissions, drawerMode,
  draft, packages,
} = useExhibitorContext()

const isAdd = computed(() => drawerMode.value === 'add')

const packageName = computed(() => {
  const id = draft.package_id
  if (id === '' || id == null) return null
  return packages.value.find(p => String(p.id) === String(id))?.name ?? null
})

// Always expand to the full Showcase catalogue so newer keys (Leads & analytics)
// appear even when the booth/package freeze was saved with an older, shorter list.
watch(
  entitlements,
  (list) => {
    if (list.length !== ALL_FEATURES.length) {
      entitlements.value = mergeFeatures(list)
    }
  },
  { immediate: true },
)

const enabledCount = computed(() => entitlements.value.filter(f => f.enabled).length)
const allEnabled = computed(() =>
  entitlements.value.length > 0 && entitlements.value.every(f => f.enabled),
)

const BOOTH_KEYS = new Set([
  'teams', 'projects', 'products', 'documents', 'videos', 'cta', 'meetings', 'lounge',
])

const boothFeatures = computed(() => entitlements.value.filter(f => BOOTH_KEYS.has(f.key)))
const leadFeatures = computed(() => entitlements.value.filter(f => !BOOTH_KEYS.has(f.key)))

function onToggle(f: FeatureLine, v: boolean) {
  f.enabled = v
  if (v && featureCountable(f.key) && f.limit < 1) f.limit = 1
}

function toggleAll() {
  const enabled = !allEnabled.value
  for (const feature of entitlements.value) {
    feature.enabled = enabled
    if (enabled && featureCountable(feature.key) && feature.limit < 1) {
      feature.limit = 1
    }
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between gap-3 mb-4">
      <div>
        <p class="font-semibold text-[.92rem] m-0 text-ink">Permissions</p>
        <p class="muted text-[.8rem] mt-1 mb-0">{{ enabledCount }} of {{ entitlements.length }} enabled</p>
      </div>
      <button type="button" class="btn sm" :disabled="!entitlements.length" @click="toggleAll">
        {{ allEnabled ? 'DISABLE ALL' : 'ENABLE ALL' }}
      </button>
    </div>

    <div class="rounded-xl border border-line bg-[#f7f8fa] px-4 py-3 mb-4">
      <p class="muted text-[.84rem] m-0">
        <template v-if="packageName">
          Seeded from <span class="font-semibold text-ink">{{ packageName }}</span>.
          Saving stores a copy on this exhibitor — later package edits won’t change this booth.
        </template>
        <template v-else>
          Starts from the selected package. Saving stores a copy on this exhibitor — later package edits won’t change this booth.
        </template>
      </p>
    </div>

    <div v-if="isAdd && !draft.package_id" class="rounded-xl border border-dashed border-line px-4 py-8 text-center mb-2">
      <p class="muted text-[.88rem] m-0">Pick a package on the Details tab to load its permissions.</p>
    </div>

    <div v-else class="flex flex-col gap-5">
      <section>
        <p class="text-[.78rem] font-semibold uppercase tracking-wider text-muted m-0 mb-2">Booth features</p>
        <div class="flex flex-col gap-2">
          <div
            v-for="f in boothFeatures" :key="f.key"
            class="flex items-center gap-3 px-4 py-2.5 border border-line rounded-xl bg-[#fafbfc]"
            :class="{ 'bg-[#F0EEFD] border-brand/20': f.enabled }"
          >
            <AppCheckbox
              :model-value="f.enabled"
              :label="featureLabel(f.key)"
              class="flex-1 [&_span]:font-medium"
              @update:model-value="onToggle(f, $event)"
            />
            <div
              v-if="featureCountable(f.key)"
              class="flex items-center shrink-0 border border-[#d7dae1] rounded-xl overflow-hidden bg-white"
              :class="{ 'opacity-50': !f.enabled }"
            >
              <button
                type="button"
                class="w-9 h-9 flex items-center justify-center text-[1.1rem] text-muted border-0 bg-transparent cursor-pointer disabled:cursor-not-allowed"
                :disabled="!f.enabled"
                :aria-label="`Decrease ${featureLabel(f.key)} limit`"
                @click="f.limit = Math.max(0, f.limit - 1)"
              >−</button>
              <span class="w-8 h-9 flex items-center justify-center text-[.91rem] font-semibold border-x border-[#d7dae1] select-none">{{ f.limit }}</span>
              <button
                type="button"
                class="w-9 h-9 flex items-center justify-center text-[1.1rem] text-muted border-0 bg-transparent cursor-pointer disabled:cursor-not-allowed"
                :disabled="!f.enabled"
                :aria-label="`Increase ${featureLabel(f.key)} limit`"
                @click="f.limit++"
              >+</button>
            </div>
          </div>
        </div>
      </section>

      <section>
        <p class="text-[.78rem] font-semibold uppercase tracking-wider text-muted m-0 mb-2">Leads &amp; analytics</p>
        <div class="flex flex-col gap-2">
          <div
            v-for="f in leadFeatures" :key="f.key"
            class="flex items-center gap-3 px-4 py-2.5 border border-line rounded-xl bg-[#fafbfc]"
            :class="{ 'bg-[#F0EEFD] border-brand/20': f.enabled }"
          >
            <AppCheckbox
              :model-value="f.enabled"
              :label="featureLabel(f.key)"
              class="flex-1 [&_span]:font-medium"
              @update:model-value="onToggle(f, $event)"
            />
          </div>
          <p v-if="!leadFeatures.length" class="muted text-[.84rem] m-0">No lead permissions in the catalogue.</p>
        </div>
      </section>
    </div>

    <p v-if="subError" class="error mt-3">{{ subError }}</p>
    <p v-if="isAdd" class="muted text-[.84rem] pt-4 mt-2">
      A full copy is saved on this exhibitor when you create them.
    </p>
    <div v-else class="flex pt-4 mt-2">
      <button class="btn flex-1 py-3 tracking-widest" :disabled="subSaving" @click="savePermissions">
        {{ subSaving ? 'SAVING…' : 'SAVE PERMISSIONS' }}
      </button>
    </div>
  </div>
</template>
