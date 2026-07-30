<script setup lang="ts">
interface BrandingColors {
  nav_bg:         string
  nav_text:       string
  primary_button: string
  body_text:      string
  page_bg:        string
  content_bg:     string
}

const DEFAULT_BRANDING_COLORS: BrandingColors = {
  nav_bg:         '#FFFFFF',
  nav_text:       '#212529',
  primary_button: '#6452E7',
  body_text:      '#FFFFFF',
  page_bg:        '#F7F7FB',
  content_bg:     '#FFFFFF',
}

/** Button label color: white on brand purple, otherwise dark gray. */
function bodyTextForPrimary(primary: string): string {
  return primary.trim().toLowerCase() === '#6452e7' ? '#FFFFFF' : '#4D5154'
}

defineProps<{ colors: BrandingColors }>()

const emit = defineEmits<{
  (e: 'update', v: Partial<BrandingColors>): void
}>()

function set<K extends keyof BrandingColors>(key: K, value: string) {
  if (key === 'primary_button') {
    emit('update', {
      primary_button: value,
      body_text: bodyTextForPrimary(value),
    })
    return
  }
  emit('update', { [key]: value } as Partial<BrandingColors>)
}

function resetToDefault() {
  emit('update', { ...DEFAULT_BRANDING_COLORS })
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Colors -->
    <div>
      <div class="flex items-center justify-between gap-3 mb-3 max-w-xl">
        <h2 class="text-[1.05rem] font-bold text-ink mb-0">Colors</h2>
        <button type="button" class="btn ghost sm" @click="resetToDefault">
          Reset to default
        </button>
      </div>
      <div class="grid grid-cols-2 gap-5 max-w-xl">
        <BrandingColorField
          label="Nav Background"
          :model-value="colors.nav_bg"
          placeholder="#FFFFFF"
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
          placeholder="#FFFFFF"
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
          placeholder="#FFFFFF"
          @update:model-value="set('content_bg', $event)"
        />
      </div>
    </div>
  </div>
</template>
