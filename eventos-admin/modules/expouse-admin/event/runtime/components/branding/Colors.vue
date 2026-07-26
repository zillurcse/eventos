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
  <div class="flex flex-col gap-6">
    <!-- Colors -->
    <div>
      <h2 class="text-[1.05rem] font-bold text-ink mb-3">Colors</h2>
      <div class="grid grid-cols-2 gap-5 max-w-xl">
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

    <!-- Action & Content -->
    <div>
      <h2 class="text-[1.05rem] font-bold text-ink mb-3">Action &amp; Content</h2>
      <div class="grid grid-cols-2 gap-5 max-w-xl">
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

    <!-- Background -->
    <div>
      <h2 class="text-[1.05rem] font-bold text-ink mb-3">Background</h2>
      <div class="grid grid-cols-2 gap-5 max-w-xl">
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
</template>
