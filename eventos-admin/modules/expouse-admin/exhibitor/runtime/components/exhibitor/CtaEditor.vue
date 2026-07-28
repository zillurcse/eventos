<script setup lang="ts">
const { eventId, draft, addCta, addCtaVideo, removeCtaVideo } = useExhibitorContext()

function onBannerChange(cta: CtaItem, v: string | string[] | null) {
  cta.image_url = (Array.isArray(v) ? v[0] : v) || ''
}
function onBannerUploaded(cta: CtaItem, v: { id: number; url: string }) {
  cta.image_file_id = v.id
  if (v.url) cta.image_url = v.url
}

function typeLabel(t: CtaType) {
  return CTA_TYPES.find(x => x.value === t)?.label ?? t
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mt-4 mb-2">
      <label class="m-0 text-ink font-semibold text-[.92rem]">CTA</label>
      <button type="button" class="btn sm" @click="addCta">ADD CTA</button>
    </div>

    <div v-for="(cta, i) in draft.cta" :key="cta.id" class="border border-line rounded-xl mb-2 overflow-hidden">
      <div class="flex items-center gap-2 px-4 py-3 bg-[#f7f8fa] cursor-pointer" @click="cta.open = !cta.open">
        <span class="font-bold text-[.9rem]">CTA {{ i + 1 }}</span>
        <span class="bg-white border border-line rounded px-2 py-0.5 text-[.78rem] font-semibold capitalize">{{ typeLabel(cta.type) }}</span>
        <div class="flex-1" />
        <button type="button" class="border-0 bg-transparent cursor-pointer text-[#dc2626] p-1" @click.stop="draft.cta.splice(i, 1)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-muted transition-transform" :class="cta.open ? 'rotate-180' : ''"><path d="M6 9l6 6 6-6"/></svg>
      </div>

      <div v-if="cta.open" class="p-4 border-t border-line flex flex-col gap-3">
        <!-- CTA Type -->
        <div>
          <label class="block mb-1.5">CTA Type</label>
          <div class="grid grid-cols-3 gap-3">
            <label
              v-for="opt in CTA_TYPES" :key="opt.value"
              class="flex items-center gap-2.5 px-4 py-3 rounded-lg border cursor-pointer text-[.92rem] font-medium transition-colors"
              :class="cta.type === opt.value ? 'border-brand bg-[#F0EEFD] text-brand-dark' : 'border-line text-ink'"
            >
              <input v-model="cta.type" type="radio" :value="opt.value" class="sr-only">
              <span
                class="w-5 h-5 rounded-full border-2 grid place-items-center shrink-0"
                :class="cta.type === opt.value ? 'border-brand' : 'border-[#cdd2dc]'"
              >
                <span v-if="cta.type === opt.value" class="w-2.5 h-2.5 rounded-full bg-brand" />
              </span>
              {{ opt.label }}
            </label>
          </div>
        </div>

        <!-- TEXT -->
        <template v-if="cta.type === 'text'">
          <AppInput v-model="cta.title" label="CTA Title" placeholder="CTA Title" />
          <FormField label="Description">
            <SessionDescriptionEditor v-model="cta.description" />
          </FormField>
          <AppInput v-model="cta.button_label" label="CTA Button Label" placeholder="CTA Button Label" />
          <AppInput v-model="cta.button_link" label="CTA Button Link" placeholder="https://" />
        </template>

        <!-- IMAGE -->
        <template v-else-if="cta.type === 'image'">
          <AppInput v-model="cta.title" label="CTA Title" placeholder="CTA Title" />
          <FormField label="CTA Banner" hint="Recommended size 320×200 px.">
            <ImageField
              :model-value="cta.image_url || null"
              :aspect="1.6"
              :output-width="320"
              :output-height="200"
              collection="ctas"
              card-width="240px"
              :gallery-path="`/events/${eventId}/gallery`"
              @update:model-value="onBannerChange(cta, $event)"
              @uploaded="onBannerUploaded(cta, $event)"
            />
          </FormField>
          <AppInput v-model="cta.button_link" label="Link" placeholder="https://" />
        </template>

        <!-- VIDEO -->
        <template v-else>
          <div class="flex items-center justify-between">
            <label class="m-0">CTA Videos</label>
            <button type="button" class="text-brand text-[.84rem] font-semibold bg-transparent border-0 cursor-pointer" @click="addCtaVideo(cta)">
              ADD VIDEO LINK
            </button>
          </div>

          <div v-for="(v, vi) in cta.videos" :key="vi" class="relative border border-line rounded-xl p-3">
            <button
              type="button"
              class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-[#dc2626] text-white text-xs leading-none flex items-center justify-center cursor-pointer border-0"
              title="Remove"
              @click="removeCtaVideo(cta, vi)"
            >×</button>
            <div class="flex gap-2">
              <AppSelect v-model="v.platform" :options="CTA_VIDEO_PLATFORMS" class="w-36 shrink-0" />
              <AppInput v-model="v.url" class="flex-1" placeholder="Enter URL" />
            </div>
            <AppInput v-model="v.caption" class="mt-2" placeholder="Enter Video Caption" />
          </div>

          <p v-if="!cta.videos.length" class="muted text-[.84rem] py-2 m-0">
            No videos yet. Click <strong>ADD VIDEO LINK</strong>.
          </p>
        </template>
      </div>
    </div>
  </div>
</template>
