<script setup lang="ts">
import type { ChatConversationItem, ChatMessageItem } from '~/stores/chat'

const props = defineProps<{
  conversation: ChatConversationItem | null
  messages: ChatMessageItem[]
  loading?: boolean
  sending?: boolean
}>()

const emit = defineEmits<{ (e: 'send', body: string): void }>()

const draft = ref('')
const scroller = ref<HTMLElement | null>(null)

function submit() {
  const body = draft.value.trim()
  if (!body || props.sending) return
  emit('send', body)
  draft.value = ''
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    submit()
  }
}

// Pin the view to the newest message when the thread changes or grows.
watch(() => [props.conversation?.id, props.messages.length], async () => {
  await nextTick()
  if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight
})

/** "Today" / "Yesterday" / date — day separators like every messenger. */
function dayLabel(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const today = new Date()
  const yesterday = new Date(today.getTime() - 86_400_000)
  const same = (x: Date, y: Date) => x.toDateString() === y.toDateString()
  if (same(d, today)) return 'Today'
  if (same(d, yesterday)) return 'Yesterday'
  return d.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' })
}

function showDay(i: number): boolean {
  if (i === 0) return true
  return dayLabel(props.messages[i]!.created_at) !== dayLabel(props.messages[i - 1]!.created_at)
}

function clock(iso: string | null): string {
  return iso ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''
}

/** Index of my newest message the counterpart has read (gets the "Seen" mark). */
const lastSeenIndex = computed(() => {
  for (let i = props.messages.length - 1; i >= 0; i--) {
    const m = props.messages[i]!
    if (m.mine) return m.read_at ? i : -1
  }
  return -1
})
</script>

<template>
  <section class="thread">
    <!-- Empty state (no thread selected) -->
    <div v-if="!conversation" class="blank">
      <svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z" /></svg>
      <p>Select a conversation<br>or start a new chat.</p>
    </div>

    <template v-else>
      <header class="head">
        <span class="av">
          <img v-if="conversation.with.avatar_url" :src="conversation.with.avatar_url" :alt="conversation.with.name">
          <template v-else>{{ initials(conversation.with.name) }}</template>
        </span>
        <div class="who">
          <strong>{{ conversation.with.name }}</strong>
          <small>
            <span class="role">{{ conversation.with.role }}</span>
            <template v-if="conversation.with.job_title"> · {{ conversation.with.job_title }}</template>
            <template v-if="conversation.with.company"> · {{ conversation.with.company }}</template>
          </small>
        </div>
      </header>

      <div ref="scroller" class="scroll">
        <div v-if="loading" class="note">Loading messages…</div>
        <div v-else-if="!messages.length" class="note">No messages yet.</div>

        <template v-for="(m, i) in messages" :key="m.id">
          <div v-if="showDay(i)" class="day"><span>{{ dayLabel(m.created_at) }}</span></div>
          <div class="row" :class="{ mine: m.mine }">
            <div class="bubble">
              <p v-if="m.body">{{ m.body }}</p>
              <ChatAttachments v-if="m.attachments?.length" :attachments="m.attachments" class="batts" />
              <span class="meta">{{ clock(m.created_at) }}</span>
            </div>
          </div>
          <div v-if="i === lastSeenIndex" class="seen">Seen</div>
        </template>
      </div>

      <footer class="composer">
        <textarea
          v-model="draft"
          rows="1"
          placeholder="Write a message…"
          @keydown="onKeydown"
        />
        <button type="button" :disabled="!draft.trim() || sending" title="Send" @click="submit">
          <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z" /></svg>
        </button>
      </footer>
    </template>
  </section>
</template>

<style scoped>
.thread { display: flex; flex-direction: column; min-height: 0; background: #f8fafc; }

.blank { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; color: #94a3b8; text-align: center; }
.blank svg { width: 46px; height: 46px; fill: none; stroke: #cbd5e1; stroke-width: 1.4; stroke-linecap: round; stroke-linejoin: round; }
.blank p { margin: 0; font-size: .9rem; line-height: 1.5; }

.head { display: flex; align-items: center; gap: 11px; padding: 12px 18px; background: #fff; border-bottom: 1px solid #eef0f3; }
.av { flex: 0 0 auto; width: 40px; height: 40px; border-radius: 50%; background: var(--brand-primary); color: #fff; font-weight: 700; font-size: .8rem; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.who { display: flex; flex-direction: column; min-width: 0; }
.who strong { color: #1e293b; font-size: .92rem; }
.who small { color: #94a3b8; font-size: .76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.who .role { text-transform: capitalize; color: var(--brand-primary); font-weight: 700; }

.scroll { flex: 1; overflow-y: auto; min-height: 0; padding: 16px 18px; display: flex; flex-direction: column; gap: 6px; }
.note { color: #94a3b8; font-size: .84rem; text-align: center; padding: 30px 0; }

.day { display: flex; justify-content: center; margin: 10px 0 6px; }
.day span { background: #e2e8f0; color: #475569; font-size: .68rem; font-weight: 700; border-radius: 999px; padding: 3px 12px; }

.row { display: flex; }
.row.mine { justify-content: flex-end; }
.bubble { max-width: 66%; background: #fff; border: 1px solid #eef0f3; border-radius: 14px 14px 14px 4px; padding: 8px 12px 6px; }
.row.mine .bubble { background: var(--brand-primary); border-color: var(--brand-primary); border-radius: 14px 14px 4px 14px; }
.bubble p { margin: 0; color: #334155; font-size: .88rem; line-height: 1.45; white-space: pre-wrap; word-break: break-word; }
.row.mine .bubble p { color: #fff; }
.batts { min-width: 200px; }
.meta { display: block; text-align: right; font-size: .64rem; color: #94a3b8; margin-top: 3px; }
.row.mine .meta { color: rgba(255,255,255,.75); }

.seen { text-align: right; color: #94a3b8; font-size: .66rem; font-weight: 600; padding-right: 4px; }

.composer { display: flex; align-items: flex-end; gap: 10px; padding: 12px 16px; background: #fff; border-top: 1px solid #eef0f3; }
.composer textarea { flex: 1; border: 1px solid #e2e8f0; border-radius: 14px; padding: 10px 14px; font: inherit; font-size: .9rem; color: #334155; outline: none; resize: none; max-height: 120px; }
.composer textarea:focus { border-color: var(--brand-primary); }
.composer button { flex: 0 0 auto; width: 42px; height: 42px; border: none; border-radius: 50%; background: var(--brand-primary); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.composer button:disabled { opacity: .45; cursor: default; }
.composer button svg { width: 19px; height: 19px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
</style>
