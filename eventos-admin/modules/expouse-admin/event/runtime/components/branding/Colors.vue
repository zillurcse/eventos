<script setup lang="ts">
export interface BrandingColors {
  nav_bg:         string
  nav_text:       string
  primary_button: string
  body_text:      string
  page_bg:        string
  content_bg:     string
}

defineProps<{ colors: BrandingColors }>()

const emit = defineEmits<{
  (e: 'update', v: Partial<BrandingColors>): void
}>()

function set<K extends keyof BrandingColors>(key: K, value: string) {
  emit('update', { [key]: value } as Partial<BrandingColors>)
}
</script>

<template>
  <div class="card">
    <!-- Section header -->
    <div class="flex items-center gap-2.5 mb-5">
      <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>
        </svg>
      </div>
      <div>
        <h2 class="mb-0!">Colors</h2>
        <p class="text-[.8rem] text-muted mt-0.5">Select your desired theme colors for your web app.</p>
      </div>
    </div>

    <div class="flex flex-col gap-6">
      <!-- Colors -->
      <div>
        <p class="text-[.8rem] font-semibold text-ink mb-3">Colors</p>
        <div class="grid grid-cols-2 gap-6">
          <BrandingColorField
            label="Nav Background"
            :model-value="colors.nav_bg"
            placeholder="#F7F7FB"
            @update:model-value="set('nav_bg', $event)"
          />
          <BrandingColorField
            label="Nav Text"
            :model-value="colors.nav_text"
            placeholder="#212529"
            @update:model-value="set('nav_text', $event)"
          />
        </div>
      </div>

      <div class="h-px bg-line" />

      <!-- Action & Content -->
      <div>
        <p class="text-[.8rem] font-semibold text-ink mb-3">Action &amp; Content</p>
        <div class="grid grid-cols-2 gap-6">
          <BrandingColorField
            label="Primary Button"
            :model-value="colors.primary_button"
            placeholder="#6452E7"
            @update:model-value="set('primary_button', $event)"
          />
          <BrandingColorField
            label="Body Text"
            :model-value="colors.body_text"
            placeholder="#212529"
            @update:model-value="set('body_text', $event)"
          />
        </div>
      </div>

      <div class="h-px bg-line" />

      <!-- Background -->
      <div>
        <p class="text-[.8rem] font-semibold text-ink mb-3">Background</p>
        <div class="grid grid-cols-2 gap-6">
          <BrandingColorField
            label="Page Background"
            :model-value="colors.page_bg"
            placeholder="#F7F7FB"
            @update:model-value="set('page_bg', $event)"
          />
          <BrandingColorField
            label="Content Block Background"
            :model-value="colors.content_bg"
            placeholder="#F0EEFD"
            @update:model-value="set('content_bg', $event)"
          />
        </div>
      </div>
    </div>
  </div>
</template>
