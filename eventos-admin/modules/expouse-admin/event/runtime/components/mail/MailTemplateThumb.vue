<script setup lang="ts">
/**
 * Live thumbnail for a gallery card: the template's cached compiled HTML,
 * rendered in a sandboxed iframe and scaled down with a CSS transform.
 *
 * Rendering the real HTML avoids a rasterization pipeline (a headless browser
 * in the API container, a re-render on every save, storage for the images) and
 * is always in sync with the template. The cost is one small request per card,
 * so it only fires when the card actually scrolls into view.
 */
const props = defineProps<{ templateId: string }>()

const api = useApi()

const root = ref<HTMLElement | null>(null)
const html = ref('')
const state = ref<'idle' | 'loading' | 'ready' | 'error'>('idle')

/** The iframe renders at full email width, then scales into the card. */
const RENDER_WIDTH = 600
const RENDER_HEIGHT = 760

let observer: IntersectionObserver | null = null

async function load() {
  if (state.value !== 'idle') return
  state.value = 'loading'
  try {
    html.value = (await api<{ html: string }>(`/email-templates/${props.templateId}/html`)).html
    state.value = html.value ? 'ready' : 'error'
  } catch {
    state.value = 'error'
  }
}

onMounted(() => {
  if (!root.value) return

  // No IntersectionObserver (old browser, jsdom) — just load immediately.
  if (typeof IntersectionObserver === 'undefined') { load(); return }

  observer = new IntersectionObserver((entries) => {
    if (entries.some(e => e.isIntersecting)) {
      load()
      observer?.disconnect()
    }
  }, { rootMargin: '200px' })

  observer.observe(root.value)
})

onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <div ref="root" class="h-[120px] overflow-hidden bg-[#f7f7fb] border-b border-line relative">
    <!-- Placeholder covers idle/loading/error alike: the card must never look broken. -->
    <div v-if="state !== 'ready'" class="absolute inset-0 grid place-items-center">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#a99ff0" stroke-width="1.6">
        <rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" />
      </svg>
    </div>

    <iframe
      v-if="state === 'ready'"
      :srcdoc="html"
      sandbox=""
      title="Template preview"
      tabindex="-1"
      aria-hidden="true"
      class="origin-top-left border-0 pointer-events-none"
      :style="{
        width: RENDER_WIDTH + 'px',
        height: RENDER_HEIGHT + 'px',
        transform: `scale(${240 / RENDER_WIDTH})`,
      }"
    />
  </div>
</template>
