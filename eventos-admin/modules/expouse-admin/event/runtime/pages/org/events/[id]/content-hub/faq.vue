<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Faq {
  id: string
  question: string
  answer: string
}

const faqs = ref<Faq[]>([])
const expanded = ref<string | null>(null)
const drawerOpen = ref(false)
const editingId = ref<string | null>(null)
const saved = ref(false)

const draft = reactive<Faq>({ id: '', question: '', answer: '' })

async function load() {
  try { faqs.value = (await api<any>(`/events/${id}/settings`)).data.faqs || [] } catch { /* */ }
}

async function persist() {
  await api(`/events/${id}/settings`, { method: 'PUT', body: { faqs: JSON.parse(JSON.stringify(faqs.value)) } })
  saved.value = true; setTimeout(() => (saved.value = false), 1500)
}

function openAdd() {
  editingId.value = null
  Object.assign(draft, { id: 'f' + Date.now(), question: '', answer: '' })
  drawerOpen.value = true
}

function openEdit(f: Faq) {
  editingId.value = f.id
  Object.assign(draft, { ...f })
  drawerOpen.value = true
}

async function saveDraft() {
  if (!draft.question.trim() || !draft.answer.trim()) return
  const clean: Faq = JSON.parse(JSON.stringify(draft))
  clean.question = clean.question.trim()
  clean.answer = clean.answer.trim()
  if (editingId.value) {
    const i = faqs.value.findIndex((f: Faq) => f.id === editingId.value)
    if (i >= 0) faqs.value[i] = clean
  } else {
    faqs.value.push(clean)
  }
  await persist()
  drawerOpen.value = false
}

async function removeFaq(f: Faq) {
  if (!confirm(`Remove this question?\n\n"${f.question}"`)) return
  faqs.value = faqs.value.filter((x: Faq) => x.id !== f.id)
  await persist()
}

async function move(index: number, dir: -1 | 1) {
  const target = index + dir
  if (target < 0 || target >= faqs.value.length) return
  const arr = faqs.value
  ;[arr[index], arr[target]] = [arr[target], arr[index]]
  await persist()
}

function toggle(f: Faq) {
  expanded.value = expanded.value === f.id ? null : f.id
}

onMounted(load)
</script>

<template>
  <div class="max-w-[760px]">
    <div class="mb-4">
      <h2 class="section-title m-0">FAQ</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Frequently asked questions shown on your event website.</p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-5">
        <div>
          <div class="font-bold text-base">
            Questions &amp; Answers
            <span v-if="saved" class="badge active ml-2">saved ✓</span>
          </div>
          <div class="muted text-[.84rem]">Help attendees by answering common questions.</div>
        </div>
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          ADD QUESTION
        </button>
      </div>

      <!-- FAQ accordion list -->
      <div v-if="faqs.length" class="flex flex-col gap-2.5">
        <div
          v-for="(f, i) in faqs" :key="f.id"
          class="border border-line rounded-xl overflow-hidden bg-white"
        >
          <div
            class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-[#fafafb]"
            @click="toggle(f)"
          >
            <svg
              width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
              stroke-linecap="round" stroke-linejoin="round"
              class="shrink-0 text-muted transition-transform duration-150"
              :class="expanded === f.id ? 'rotate-90' : ''"
            ><path d="M9 18l6-6-6-6"/></svg>
            <div class="font-semibold text-[.92rem] text-[#1a1a2e] flex-1 min-w-0">{{ f.question }}</div>

            <div class="flex items-center gap-1 shrink-0" @click.stop>
              <button
                class="icon-btn" title="Move up"
                :disabled="i === 0"
                @click="move(i, -1)"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6"/></svg>
              </button>
              <button
                class="icon-btn" title="Move down"
                :disabled="i === faqs.length - 1"
                @click="move(i, 1)"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <button class="icon-btn" title="Edit" @click="openEdit(f)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              </button>
              <button class="icon-btn danger" title="Remove" @click="removeFaq(f)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
              </button>
            </div>
          </div>

          <div v-if="expanded === f.id" class="px-4 pb-4 pt-0 pl-[43px]">
            <p class="muted text-[.88rem] whitespace-pre-line m-0 leading-relaxed">{{ f.answer }}</p>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-13 px-5">
        <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="10"/></svg>
        </div>
        <p class="muted m-0 mb-3">No questions yet. Add your first FAQ to help attendees.</p>
        <button class="btn" @click="openAdd">+ ADD QUESTION</button>
      </div>
    </div>

    <!-- Add / Edit drawer -->
    <Drawer
      v-if="drawerOpen"
      :title="editingId ? 'Edit Question' : 'Add Question'"
      @close="drawerOpen = false"
    >
      <div class="mb-4">
        <AppInput v-model="draft.question" label="Question" required placeholder="e.g. What time do doors open?" />
      </div>

      <div class="mb-1">
        <AppTextarea v-model="draft.answer" label="Answer" required :rows="6" placeholder="Write the answer attendees will see…" />
      </div>

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!draft.question.trim() || !draft.answer.trim()" @click="saveDraft">
          {{ editingId ? 'UPDATE' : 'ADD' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  color: var(--muted, #6b7280);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background .15s, color .15s;
}
.icon-btn:hover:not(:disabled) {
  background: #f3f0ff;
  color: #6352e7;
}
.icon-btn.danger:hover:not(:disabled) {
  background: #fef2f2;
  color: #dc2626;
}
.icon-btn:disabled {
  opacity: .35;
  cursor: not-allowed;
}
</style>
