<script setup lang="ts">
/**
 * Read-only view of the compiled HTML the recipient will actually receive.
 * Useful for handing markup to a deliverability tool, diffing what a change
 * did, or pasting into an inbox-preview service.
 *
 * Read-only by design: the HTML is derived from the block tree on the server,
 * so anything edited here would be overwritten on the next save.
 */
const props = defineProps<{ html: string, loading?: boolean }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const copied = ref(false)

const sizeLabel = computed(() => {
  const bytes = new Blob([props.html]).size
  return bytes < 1024 ? `${bytes} B` : `${(bytes / 1024).toFixed(1)} KB`
})

/**
 * Gmail truncates messages over ~102 KB and shows a "View entire message"
 * link, which also cuts off open tracking — worth warning about.
 */
const tooLargeForGmail = computed(() => new Blob([props.html]).size > 102_000)

async function copy() {
  try {
    await navigator.clipboard.writeText(props.html)
    copied.value = true
    setTimeout(() => { copied.value = false }, 1800)
  } catch { /* clipboard blocked — the user can still select the text */ }
}

function download() {
  const url = URL.createObjectURL(new Blob([props.html], { type: 'text/html' }))
  const a = document.createElement('a')
  a.href = url
  a.download = 'email.html'
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="fixed inset-0 z-[170] bg-black/40 grid place-items-center p-4" @click.self="emit('close')">
    <div class="bg-white rounded-2xl shadow-xl w-[860px] max-w-full max-h-[84vh] flex flex-col">
      <header class="flex items-center gap-3 p-4 border-b border-line">
        <div class="flex-1">
          <h3 class="m-0 text-[1.05rem]">Compiled HTML</h3>
          <p class="muted text-[.8rem] m-0">
            Read-only — generated from your blocks. {{ sizeLabel }}
          </p>
        </div>
        <button class="btn ghost sm" :disabled="!html" @click="copy">{{ copied ? 'Copied ✓' : 'Copy' }}</button>
        <button class="btn ghost sm" :disabled="!html" @click="download">Download</button>
        <button class="w-8 h-8 rounded-lg border border-line grid place-items-center cursor-pointer hover:bg-[#f5f5fa]" title="Close" @click="emit('close')">✕</button>
      </header>

      <p v-if="tooLargeForGmail" class="m-0 px-4 py-2 bg-[#fef3c7] text-[#92400e] text-[.8rem] border-b border-line">
        ⚠ Over 102 KB — Gmail will clip this message and hide the end behind a “View entire message” link.
      </p>

      <div class="flex-1 overflow-auto bg-[#1f2430] p-4">
        <p v-if="loading" class="text-[#a8aec0] text-center py-10 m-0">Compiling…</p>
        <pre v-else class="m-0 text-[#e2e8f0] text-[.74rem] leading-relaxed whitespace-pre-wrap break-all font-mono">{{ html }}</pre>
      </div>
    </div>
  </div>
</template>
