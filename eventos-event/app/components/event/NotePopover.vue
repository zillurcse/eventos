<script setup lang="ts">
import type { NoteType } from '~/stores/notes'

/**
 * Small "jot a note" popup, opened from a notepad icon on a Speaker /
 * Session / Delegate card. Teleported to <body> and positioned from the
 * trigger's bounding rect, so it survives cards with `overflow: hidden`
 * (delegates) or hover-only visibility (speakers' .reveal actions).
 */
const props = defineProps<{ type: NoteType, id: string, calendarLink?: string | null }>()

const notes = useNotesStore()
const bookmarks = useBookmarksStore()

const hasNote = computed(() => !!notes.noteFor(props.type, props.id))
const bookmarked = computed(() => bookmarks.isOn(props.type, props.id))

const isOpen = ref(false)
const draft = ref('')
const saving = ref(false)
const trigger = ref<HTMLElement | null>(null)
const pop = ref<HTMLElement | null>(null)
const style = ref<Record<string, string>>({})

function place() {
  const btn = trigger.value
  if (!btn) return
  const rect = btn.getBoundingClientRect()
  const width = 272
  let left = rect.right - width
  left = Math.max(12, Math.min(left, window.innerWidth - width - 12))
  let top = rect.bottom + 8
  const maxTop = window.innerHeight - 340
  if (top > maxTop) top = Math.max(12, rect.top - 8 - 320)
  style.value = { top: `${top}px`, left: `${left}px` }
}

async function open() {
  if (!useAuthStore().isAuthed) return
  draft.value = notes.noteFor(props.type, props.id)?.text ?? ''
  isOpen.value = true
  await nextTick()
  place()
  window.addEventListener('resize', place)
  document.addEventListener('click', onOutside, true)
}

function close() {
  isOpen.value = false
  window.removeEventListener('resize', place)
  document.removeEventListener('click', onOutside, true)
}

function onOutside(e: MouseEvent) {
  const t = e.target as Node
  if (pop.value && !pop.value.contains(t) && trigger.value && !trigger.value.contains(t)) close()
}

async function save() {
  const text = draft.value.trim()
  if (!text || saving.value) return close()
  saving.value = true
  try {
    await notes.save(props.type, props.id, text)
    close()
  } finally {
    saving.value = false
  }
}

onBeforeUnmount(() => {
  window.removeEventListener('resize', place)
  document.removeEventListener('click', onOutside, true)
})
</script>

<template>
  <button
    ref="trigger" class="act note" :class="{ on: hasNote }" type="button"
    :title="hasNote ? 'Edit note' : 'Add note'" @click.stop="isOpen ? close() : open()"
  >
    <svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h5" /></svg>
  </button>

  <Teleport to="body">
    <div v-if="isOpen" ref="pop" class="note-pop" :style="style">
      <div class="pop-head">
        <button class="picon active" type="button" title="Note">
          <svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h5" /></svg>
        </button>
        <button class="picon" :class="{ on: bookmarked }" type="button" title="Bookmark" @click="bookmarks.toggle(type, id)">
          <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        </button>
        <a v-if="calendarLink" class="picon" :href="calendarLink" target="_blank" rel="noopener" title="Add to calendar">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18M12 14v6M9 17h6" /></svg>
        </a>
        <button class="pclose" type="button" title="Close" @click="close">
          <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
      </div>

      <textarea v-model="draft" class="pop-text" rows="7" placeholder="Write Your message here…" @keydown.esc="close" />

      <div class="pop-foot">
        <button class="pop-save" type="button" :disabled="saving || !draft.trim()" @click="save">Save and Close</button>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.act.note {
  width: 34px; height: 34px; border-radius: 10px; border: 1px solid #dfe3ea; background: #fff;
  color: var(--brand-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
}
.act.note svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.act.note.on { background: var(--brand-primary); color: #fff; border-color: var(--brand-primary); }

.note-pop {
  position: fixed; z-index: 200; width: 272px; background: #fff; border-radius: 14px;
  box-shadow: 0 16px 40px rgba(15,23,42,.22); border: 1px solid #eef0f3; overflow: hidden;
  animation: pop-in .14s ease;
}
@keyframes pop-in { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }

.pop-head { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-bottom: 1px solid #f1f2f6; }
.picon {
  width: 30px; height: 30px; border-radius: 8px; border: none; background: #f4f5f8; color: #64748b;
  display: inline-flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none;
}
.picon svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.picon.active { background: var(--brand-primary); color: #fff; }
.picon.on { color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 14%, #fff); }
.pclose {
  margin-left: auto; width: 26px; height: 26px; border-radius: 50%; border: none; background: var(--brand-primary);
  color: #fff; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
}
.pclose svg { width: 12px; height: 12px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; }

.pop-text {
  display: block; width: 100%; height: 190px; border: none; resize: none; padding: 14px; font: inherit;
  font-size: .86rem; color: #334155; box-sizing: border-box;
}
.pop-text:focus { outline: none; }
.pop-text::placeholder { color: #94a3b8; }

.pop-foot { display: flex; justify-content: flex-end; padding: 10px 14px; border-top: 1px solid #f1f2f6; }
.pop-save { border: none; background: none; color: var(--brand-primary); font: inherit; font-size: .84rem; font-weight: 700; cursor: pointer; padding: 4px; }
.pop-save:disabled { color: #cbd5e1; cursor: default; }
.pop-save:not(:disabled):hover { text-decoration: underline; }
</style>
