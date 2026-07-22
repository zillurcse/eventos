<script setup lang="ts">
import { computed } from 'vue'
import type { BuilderField } from '../../utils/profileFormTypes'

/**
 * Right pane of the profile form builder — properties of the selected field.
 * Mutates the field object in place; the parent owns dirty-tracking via a
 * snapshot comparison over the fields array.
 */
const props = defineProps<{ field: BuilderField | null, audience: string }>()

const OPTION_TYPES = ['select', 'multiselect', 'radio', 'checkbox']
const hasOptions = computed(() => !!props.field && OPTION_TYPES.includes(props.field.type))
const isBreak = computed(() => props.field?.type === 'section_break')
const isCaptcha = computed(() => props.field?.type === 'recaptcha')
const isTextlike = computed(() => ['text', 'number'].includes(props.field?.type || ''))

// "Is Number" is presented as a property of a text field but is really the
// text ↔ number type switch, so numeric validation is enforced server-side.
const isNumber = computed({
  get: () => props.field?.type === 'number',
  set: (v: boolean) => { if (props.field) props.field.type = v ? 'number' : 'text' },
})

const width = computed({
  get: () => props.field?.meta.width === 50 ? 50 : 100,
  set: (v: number) => { if (props.field) props.field.meta.width = v },
})

function surface(key: 'registration' | 'onboarding' | 'public') {
  return computed({
    get: () => (props.field?.meta.surfaces?.[key] ?? true) !== false,
    set: (v: boolean) => {
      if (!props.field) return
      props.field.meta.surfaces = { ...(props.field.meta.surfaces || {}), [key]: v }
    },
  })
}
const onRegistration = surface('registration')
const onOnboarding = surface('onboarding')
const onPublic = surface('public')

const showToOthers = computed({
  get: () => props.field?.meta.show_to_others === true,
  set: (v: boolean) => { if (props.field) props.field.meta.show_to_others = v },
})

const ratingMax = computed({
  get: () => Number(props.field?.validation?.max ?? 5),
  set: (v: number) => {
    if (!props.field) return
    props.field.validation = { ...(props.field.validation || {}), max: Math.max(1, Math.min(10, v || 5)) }
  },
})

function addOption() {
  props.field?.options.push({ label: '' })
}
function removeOption(i: number) {
  props.field?.options.splice(i, 1)
}
</script>

<template>
  <div>
    <h3 class="m-0 text-[1.02rem] font-bold text-ink">Properties</h3>
    <p class="muted text-[.8rem] mt-1 mb-4">{{ field ? 'Edit the selected field settings' : 'Select a field on the form to edit it' }}</p>

    <div v-if="!field" class="border border-dashed border-[#d7dae1] rounded-xl py-12 text-center muted text-[.85rem]">
      Nothing selected
    </div>

    <template v-else>
      <div class="border-t border-line pt-4">
        <div class="font-bold text-[.92rem] mb-3">{{ isBreak ? 'Section details' : 'Basic details' }}</div>

        <label class="block mb-1.5 text-[.83rem]">{{ isBreak ? 'Section title' : 'Field Label' }}</label>
        <input v-model="field.label" class="m-0 w-full" :placeholder="isBreak ? 'e.g. Company details' : 'e.g. First name'">
        <p v-if="!field.is_default && !isBreak && !isCaptcha" class="muted text-[.72rem] mt-1 mb-0 font-mono">key: {{ field.key }}</p>
        <p v-if="field.is_default" class="muted text-[.72rem] mt-1 mb-0">Default field — it can be hidden, but not deleted.</p>

        <template v-if="!isBreak && !isCaptcha">
          <div class="flex items-center gap-5 mt-3.5">
            <label class="flex items-center gap-2 cursor-pointer select-none text-[.86rem] font-medium">
              <input v-model="field.is_required" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]"> Mandatory
            </label>
            <label v-if="isTextlike" class="flex items-center gap-2 cursor-pointer select-none text-[.86rem] font-medium">
              <input v-model="isNumber" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]"> Is Number
            </label>
            <label v-if="['email', 'text', 'phone'].includes(field.type)" class="flex items-center gap-2 cursor-pointer select-none text-[.86rem] font-medium">
              <input v-model="field.is_unique" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]"> Unique
            </label>
          </div>

          <label class="block mb-1.5 mt-3.5 text-[.83rem]">Placeholder <span class="muted">(optional)</span></label>
          <input v-model="field.meta.placeholder" class="m-0 w-full" placeholder="Shown inside the empty input">
        </template>

        <label class="block mb-1.5 mt-3.5 text-[.83rem]">{{ isBreak ? 'Section description' : 'Help text' }} <span class="muted">(optional)</span></label>
        <input v-model="field.help_text" class="m-0 w-full" placeholder="A short hint under the field">
      </div>

      <!-- ── Options ─────────────────────────────────────────── -->
      <div v-if="hasOptions" class="border-t border-line pt-4 mt-4">
        <div class="font-bold text-[.92rem] mb-3">Options</div>
        <div v-for="(opt, i) in field.options" :key="i" class="flex items-center gap-2 mb-2">
          <input v-model="opt.label" class="m-0 flex-1" :placeholder="`Option ${i + 1}`">
          <button
            class="w-7 h-7 rounded-lg bg-transparent border-none cursor-pointer text-[#dc2626] hover:bg-[#fef2f2] grid place-items-center shrink-0"
            title="Remove option" @click="removeOption(i)"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <button class="btn ghost sm" @click="addOption">
          <AppIcon name="plus" class="w-3.5 h-3.5" /> Add option
        </button>
      </div>

      <!-- ── Rating scale ────────────────────────────────────── -->
      <div v-if="field.type === 'rating'" class="border-t border-line pt-4 mt-4">
        <label class="block mb-1.5 text-[.83rem]">Maximum stars</label>
        <input v-model.number="ratingMax" type="number" min="1" max="10" class="m-0 w-24">
      </div>

      <!-- ── Visibility & surfaces ───────────────────────────── -->
      <div v-if="!isBreak && !isCaptcha" class="border-t border-line pt-4 mt-4 flex flex-col gap-3.5">
        <div class="flex items-center justify-between gap-3">
          <span class="text-[.86rem] font-medium">Show field to other user</span>
          <button
            type="button" role="switch" :aria-checked="showToOthers"
            class="w-10 h-[22px] rounded-full border-none cursor-pointer transition-colors duration-150 relative shrink-0"
            :class="showToOthers ? 'bg-[#6352e7]' : 'bg-[#d7dae1]'"
            @click="showToOthers = !showToOthers"
          >
            <span class="absolute top-[3px] w-4 h-4 rounded-full bg-white transition-[left] duration-150" :style="{ left: showToOthers ? '21px' : '3px' }" />
          </button>
        </div>
        <p class="muted text-[.74rem] -mt-2.5 mb-0">When on, the answer appears on the attendee's public profile card.</p>

        <template v-if="audience === 'attendee'">
          <div class="flex items-center justify-between gap-3">
            <span class="text-[.86rem] font-medium">Add field to user registration</span>
            <button
              type="button" role="switch" :aria-checked="onRegistration"
              class="w-10 h-[22px] rounded-full border-none cursor-pointer transition-colors duration-150 relative shrink-0"
              :class="onRegistration ? 'bg-[#6352e7]' : 'bg-[#d7dae1]'"
              @click="onRegistration = !onRegistration"
            >
              <span class="absolute top-[3px] w-4 h-4 rounded-full bg-white transition-[left] duration-150" :style="{ left: onRegistration ? '21px' : '3px' }" />
            </button>
          </div>
          <div class="flex items-center justify-between gap-3">
            <span class="text-[.86rem] font-medium">Add field to user Onboarding</span>
            <button
              type="button" role="switch" :aria-checked="onOnboarding"
              class="w-10 h-[22px] rounded-full border-none cursor-pointer transition-colors duration-150 relative shrink-0"
              :class="onOnboarding ? 'bg-[#6352e7]' : 'bg-[#d7dae1]'"
              @click="onOnboarding = !onOnboarding"
            >
              <span class="absolute top-[3px] w-4 h-4 rounded-full bg-white transition-[left] duration-150" :style="{ left: onOnboarding ? '21px' : '3px' }" />
            </button>
          </div>
        </template>

        <div class="flex items-center justify-between gap-3">
          <span class="text-[.86rem] font-medium">Add field to Public Registration</span>
          <button
            type="button" role="switch" :aria-checked="onPublic"
            class="w-10 h-[22px] rounded-full border-none cursor-pointer transition-colors duration-150 relative shrink-0"
            :class="onPublic ? 'bg-[#6352e7]' : 'bg-[#d7dae1]'"
            @click="onPublic = !onPublic"
          >
            <span class="absolute top-[3px] w-4 h-4 rounded-full bg-white transition-[left] duration-150" :style="{ left: onPublic ? '21px' : '3px' }" />
          </button>
        </div>
        <p class="muted text-[.74rem] -mt-2.5 mb-0">The shared public form (link & embed) only shows fields with this on.</p>
      </div>

      <!-- ── Width ───────────────────────────────────────────── -->
      <div v-if="!isBreak && !isCaptcha" class="border-t border-line pt-4 mt-4">
        <div class="font-bold text-[.92rem] mb-1">Field width visibility</div>
        <p class="muted text-[.78rem] mt-0 mb-3">Choose the field width on the landing page &amp; onboarding.</p>
        <div class="flex items-center gap-6">
          <label class="flex items-center gap-2 cursor-pointer select-none text-[.88rem] font-medium">
            <input type="radio" :checked="width === 50" class="w-4 h-4 m-0 accent-[#6352e7]" @change="width = 50"> 50%
          </label>
          <label class="flex items-center gap-2 cursor-pointer select-none text-[.88rem] font-medium">
            <input type="radio" :checked="width === 100" class="w-4 h-4 m-0 accent-[#6352e7]" @change="width = 100"> 100%
          </label>
        </div>
      </div>

      <p v-if="isCaptcha" class="muted text-[.8rem] border-t border-line pt-4 mt-4">
        Adds an "I'm not a robot" verification step to the public form. Submissions
        also carry an invisible honeypot check either way.
      </p>
    </template>
  </div>
</template>
