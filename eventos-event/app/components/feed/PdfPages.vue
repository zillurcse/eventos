<script setup lang="ts">
/**
 * LinkedIn-style inline document viewer for PDF feed attachments: renders the
 * PDF page-by-page (pdf.js → canvas) with prev/next arrows, a page counter,
 * and a dot rail. Falls back to a plain download link if the file can't be
 * fetched or parsed.
 */
const props = defineProps<{ url: string, name?: string | null }>()

type PdfDoc = { numPages: number, getPage: (n: number) => Promise<any>, destroy: () => void }

const host = ref<HTMLElement | null>(null)
const canvas = ref<HTMLCanvasElement | null>(null)
const loading = ref(true)
const failed = ref(false)
const page = ref(1)
const pages = ref(0)
const rendering = ref(false)

let doc: PdfDoc | null = null
let observer: IntersectionObserver | null = null
let renderTask: { cancel: () => void, promise: Promise<void> } | null = null

async function loadDoc() {
  try {
    const pdfjs = await import('pdfjs-dist')
    pdfjs.GlobalWorkerOptions.workerSrc = new URL(
      'pdfjs-dist/build/pdf.worker.min.mjs',
      import.meta.url,
    ).toString()
    doc = await pdfjs.getDocument({ url: props.url }).promise
    pages.value = doc.numPages
    loading.value = false
    await renderPage()
  } catch {
    loading.value = false
    failed.value = true
  }
}

async function renderPage() {
  if (!doc || !canvas.value || rendering.value) return
  rendering.value = true
  try {
    const p = await doc.getPage(page.value)
    const width = host.value?.clientWidth || 560
    const base = p.getViewport({ scale: 1 })
    const scale = width / base.width
    const dpr = Math.min(window.devicePixelRatio || 1, 2)
    const viewport = p.getViewport({ scale: scale * dpr })

    const c = canvas.value
    c.width = viewport.width
    c.height = viewport.height
    c.style.width = `${Math.floor(viewport.width / dpr)}px`
    c.style.height = `${Math.floor(viewport.height / dpr)}px`

    renderTask?.cancel()
    renderTask = p.render({ canvasContext: c.getContext('2d'), viewport })
    await renderTask.promise
  } catch {
    // A cancelled render throws — ignore; a broken page keeps the last frame.
  } finally {
    rendering.value = false
  }
}

function go(delta: number) {
  const next = page.value + delta
  if (next < 1 || next > pages.value || rendering.value) return
  page.value = next
  renderPage()
}

onMounted(() => {
  // Lazy: only fetch/parse the PDF once the card scrolls near the viewport.
  observer = new IntersectionObserver((entries) => {
    if (entries.some(e => e.isIntersecting)) {
      observer?.disconnect()
      observer = null
      loadDoc()
    }
  }, { rootMargin: '200px' })
  if (host.value) observer.observe(host.value)
})

onBeforeUnmount(() => {
  observer?.disconnect()
  renderTask?.cancel()
  doc?.destroy()
})

/** Dot rail like LinkedIn — capped so huge decks don't overflow. */
const dots = computed(() => Math.min(pages.value, 10))
function dotActive(i: number) {
  if (pages.value <= 10) return i === page.value
  // Map the current page onto the capped rail.
  return i === Math.round(((page.value - 1) / (pages.value - 1)) * 9) + 1
}
</script>

<template>
  <!-- Fallback: plain document link -->
  <a v-if="failed" :href="url" target="_blank" rel="noopener" class="doc-fallback">
    <svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7zM15 3v4h4M9 13h6M9 17h6" /></svg>
    <span>{{ name || 'Open PDF' }}</span>
  </a>

  <div v-else ref="host" class="pdfdoc">
    <div class="dochead">
      <svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7zM15 3v4h4" /></svg>
      <span class="dname">{{ name || 'Document' }}</span>
      <span v-if="pages" class="dpages">{{ pages }} page{{ pages === 1 ? '' : 's' }}</span>
      <a class="dopen" :href="url" target="_blank" rel="noopener" title="Open in a new tab">
        <svg viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M9 5H5v14h14v-4" /></svg>
      </a>
    </div>

    <div class="stage">
      <div v-if="loading" class="docload">Loading document…</div>
      <canvas v-show="!loading" ref="canvas" />

      <template v-if="pages > 1">
        <button class="nav prev" type="button" :disabled="page <= 1" aria-label="Previous page" @click="go(-1)">
          <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6" /></svg>
        </button>
        <button class="nav next" type="button" :disabled="page >= pages" aria-label="Next page" @click="go(1)">
          <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
        </button>
        <span class="counter">{{ page }} / {{ pages }}</span>
      </template>
    </div>

    <div v-if="pages > 1" class="dotrail">
      <span v-for="i in dots" :key="i" class="dot" :class="{ on: dotActive(i) }" />
    </div>
  </div>
</template>

<style scoped>
.doc-fallback { display: flex; align-items: center; gap: 12px; padding: 14px 16px; color: #334155; text-decoration: none; border: 1px solid #eef0f3; border-radius: 10px; background: #f4f5f8; }
.doc-fallback:hover { background: #f1f5f9; }
.doc-fallback svg { flex: 0 0 auto; width: 26px; height: 26px; fill: none; stroke: #ef4444; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.doc-fallback span { font-weight: 600; font-size: .88rem; word-break: break-word; }

.pdfdoc { border: 1px solid #eef0f3; border-radius: 12px; overflow: hidden; background: #fff; }

.dochead { display: flex; align-items: center; gap: 9px; padding: 10px 14px; border-bottom: 1px solid #eef0f3; background: #fafbfc; }
.dochead svg { flex: 0 0 auto; width: 18px; height: 18px; fill: none; stroke: #ef4444; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.dname { flex: 1; min-width: 0; font-weight: 700; font-size: .84rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dpages { flex: 0 0 auto; color: #94a3b8; font-size: .76rem; font-weight: 600; }
.dopen { flex: 0 0 auto; display: inline-flex; padding: 4px; border-radius: 7px; color: #64748b; }
.dopen:hover { background: #eef0f3; }
.dopen svg { width: 15px; height: 15px; stroke: currentColor; }

.stage { position: relative; background: #f4f5f8; min-height: 120px; }
.stage canvas { display: block; margin: 0 auto; }
.docload { padding: 44px 0; text-align: center; color: #94a3b8; font-size: .84rem; }

.nav { position: absolute; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; border: none; border-radius: 50%; background: rgba(15,23,42,.6); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .15s ease; }
.stage:hover .nav { opacity: 1; }
.nav:disabled { opacity: 0 !important; cursor: default; }
.nav.prev { left: 10px; }
.nav.next { right: 10px; }
.nav svg { width: 19px; height: 19px; fill: none; stroke: currentColor; stroke-width: 2.1; stroke-linecap: round; stroke-linejoin: round; }

.counter { position: absolute; right: 10px; bottom: 10px; background: rgba(15,23,42,.7); color: #fff; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; }

.dotrail { display: flex; justify-content: center; gap: 5px; padding: 9px 0; border-top: 1px solid #eef0f3; background: #fafbfc; }
.dot { width: 6px; height: 6px; border-radius: 50%; background: #d7dbe2; transition: background .15s ease; }
.dot.on { background: var(--brand-primary); }
</style>
