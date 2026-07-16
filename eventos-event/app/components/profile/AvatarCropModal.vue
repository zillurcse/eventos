<script setup lang="ts">
/**
 * "Edit Photo" — crop/zoom/pan the just-picked file into a square avatar
 * before it ever reaches the server. Hand-rolled (drag + a zoom slider) rather
 * than a cropper dependency: the only op we need is "circular crop of one
 * image", which is a few dozen lines of canvas math, not worth a package.
 *
 * The square viewport is what the image fills ("cover" fit at zoom 1); the
 * circle is just a fixed guide concentric with it (CSS box-shadow-spread
 * trick, so it needs no extra image element). Panning is clamped so the
 * square viewport — and therefore the circle inside it — is always fully
 * covered; the circle's own bounding square (not the outer viewport) is what
 * actually gets read back into the output canvas.
 */
const props = defineProps<{ file: File }>()
const emit = defineEmits<{ save: [blob: Blob], cancel: [] }>()

const VIEWPORT = 300
const CIRCLE = 220
const OUTPUT = 480

const objectUrl = ref('')
const naturalW = ref(0)
const naturalH = ref(0)
const baseScale = ref(1)
const zoom = ref(1)
const dx = ref(0)
const dy = ref(0)
const dragging = ref(false)
const dragStart = { x: 0, y: 0, dx: 0, dy: 0 }

onMounted(() => {
  objectUrl.value = URL.createObjectURL(props.file)
})
onBeforeUnmount(() => {
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
})

function onImgLoad(e: Event) {
  const img = e.target as HTMLImageElement
  naturalW.value = img.naturalWidth
  naturalH.value = img.naturalHeight
  baseScale.value = Math.max(VIEWPORT / naturalW.value, VIEWPORT / naturalH.value)
  reset()
}

const actualScale = computed(() => baseScale.value * zoom.value)
const displayedW = computed(() => naturalW.value * actualScale.value)
const displayedH = computed(() => naturalH.value * actualScale.value)

function clamp() {
  const maxDx = Math.max(0, (displayedW.value - VIEWPORT) / 2)
  const maxDy = Math.max(0, (displayedH.value - VIEWPORT) / 2)
  dx.value = Math.min(maxDx, Math.max(-maxDx, dx.value))
  dy.value = Math.min(maxDy, Math.max(-maxDy, dy.value))
}

watch(zoom, clamp)

function reset() {
  zoom.value = 1
  dx.value = 0
  dy.value = 0
}

function startDrag(e: PointerEvent) {
  dragging.value = true
  dragStart.x = e.clientX
  dragStart.y = e.clientY
  dragStart.dx = dx.value
  dragStart.dy = dy.value
  ;(e.target as HTMLElement).setPointerCapture(e.pointerId)
}
function onDrag(e: PointerEvent) {
  if (!dragging.value) return
  dx.value = dragStart.dx + (e.clientX - dragStart.x)
  dy.value = dragStart.dy + (e.clientY - dragStart.y)
  clamp()
}
function endDrag() { dragging.value = false }

const imgStyle = computed(() => ({
  width: `${displayedW.value}px`,
  height: `${displayedH.value}px`,
  transform: `translate(-50%, -50%) translate(${dx.value}px, ${dy.value}px)`,
}))

function save() {
  const canvas = document.createElement('canvas')
  canvas.width = OUTPUT
  canvas.height = OUTPUT
  const ctx = canvas.getContext('2d')!

  const sSize = CIRCLE / actualScale.value
  const sx = naturalW.value / 2 - sSize / 2 - dx.value / actualScale.value
  const sy = naturalH.value / 2 - sSize / 2 - dy.value / actualScale.value

  const img = new Image()
  img.onload = () => {
    ctx.drawImage(
      img,
      Math.max(0, Math.min(naturalW.value - sSize, sx)),
      Math.max(0, Math.min(naturalH.value - sSize, sy)),
      sSize, sSize,
      0, 0, OUTPUT, OUTPUT,
    )
    canvas.toBlob(b => b && emit('save', b), 'image/jpeg', 0.92)
  }
  img.src = objectUrl.value
}
</script>

<template>
  <div class="overlay" @click.self="emit('cancel')">
    <div class="modal" role="dialog" aria-modal="true" aria-label="Edit Photo">
      <div class="head">
        <h2>Edit Photo</h2>
        <button type="button" class="close" @click="emit('cancel')">
          <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
      </div>

      <div class="body">
        <div class="stage">
          <div
            class="viewport"
            @pointerdown="startDrag"
            @pointermove="onDrag"
            @pointerup="endDrag"
            @pointercancel="endDrag"
          >
            <img v-if="objectUrl" :src="objectUrl" :style="imgStyle" draggable="false" @load="onImgLoad">
          </div>
          <div class="hole" />
        </div>

        <div class="zoom-row">
          <input v-model.number="zoom" type="range" min="1" max="3" step="0.01">
          <button type="button" class="reset" title="Reset" @click="reset">
            <svg viewBox="0 0 24 24"><path d="M4 4v6h6M20 20v-6h-6" /><path d="M5.5 15a8 8 0 1 0 1-9.5L4 9M18.5 9l2 2" /></svg>
          </button>
        </div>
      </div>

      <div class="foot">
        <button type="button" class="btn cancel" @click="emit('cancel')">Cancel</button>
        <button type="button" class="btn save" @click="save">Save</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 120; }
.modal { background: #fff; border-radius: 16px; width: 100%; max-width: 620px; overflow: hidden; box-shadow: 0 24px 60px rgba(15,23,42,.34); }

.head { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; }
.head h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #334155; }
.close { width: 40px; height: 40px; border: none; border-radius: 10px; background: var(--brand-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.close svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; }

.body { padding: 4px 24px 20px; display: flex; flex-direction: column; align-items: center; gap: 18px; }

.stage { position: relative; width: 300px; height: 300px; border-radius: 10px; overflow: hidden; background: #f1f5f9; touch-action: none; }
.viewport { position: absolute; inset: 0; overflow: hidden; cursor: grab; }
.viewport:active { cursor: grabbing; }
.viewport img { position: absolute; left: 50%; top: 50%; max-width: none; user-select: none; -webkit-user-drag: none; }
.hole {
  position: absolute; inset: 0; margin: auto; width: 220px; height: 220px; border-radius: 50%;
  box-shadow: 0 0 0 9999px rgba(15,23,42,.45);
  border: 3px solid #fff;
  pointer-events: none;
}

.zoom-row { width: 100%; display: flex; align-items: center; gap: 14px; }
.zoom-row input[type="range"] { flex: 1; accent-color: var(--brand-primary); }
.reset { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e2e8f0; background: #fff; color: #64748b; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex: 0 0 auto; }
.reset:hover { background: #f7f8fa; }
.reset svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

.foot { display: flex; justify-content: flex-end; gap: 12px; padding: 18px 24px; border-top: 1px solid #f1f2f6; }
.btn { border: none; border-radius: 10px; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; padding: 11px 24px; }
.btn.cancel { background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); }
.btn.cancel:hover { background: color-mix(in srgb, var(--brand-primary) 16%, #fff); }
.btn.save { background: var(--brand-primary); color: #fff; }
.btn.save:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
</style>
