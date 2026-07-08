<script setup lang="ts">
import type { ChatAttachment, ChatPerson } from '~/stores/chat'

/**
 * EXPOUSE-style chat slide-over from the topbar chat icon. Two internal views:
 * the Conversations list (gray header + search + red close) and a linear
 * thread (avatar + blue name + "06:57 PM | May 06" above each message — no
 * bubbles) with a rounded composer. State lives in the chat store, shared
 * with the /chat page.
 */
const chat = useChatStore()

const searchOpen = ref(false)
const filter = ref('')
const pickerOpen = ref(false)
const scroller = ref<HTMLElement | null>(null)
const draft = ref('')

// ── Attachments (image / video / pdf / doc / excel) ─────────────────────
const fileInput = ref<HTMLInputElement | null>(null)
const pending = ref<ChatAttachment[]>([])
const uploading = ref(false)

const ACCEPT = 'image/*,video/mp4,video/webm,video/quicktime,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt'

async function onFiles(e: Event) {
  const input = e.target as HTMLInputElement
  const files = Array.from(input.files ?? []).slice(0, 5 - pending.value.length)
  if (!files.length) return
  uploading.value = true
  try {
    for (const file of files) {
      const up = await chat.uploadMedia(file)
      pending.value.push({ kind: up.kind, url: up.url, name: up.filename })
    }
  } catch {
    // keep what already uploaded; the failed file is simply not attached
  } finally {
    uploading.value = false
    input.value = ''
  }
}

// activeId decides which view the drawer shows; back returns to the list.
const inThread = computed(() => !!chat.activeId && !!chat.active)

const shown = computed(() => {
  const q = filter.value.trim().toLowerCase()
  if (!q) return chat.conversations
  return chat.conversations.filter(c =>
    c.with.name.toLowerCase().includes(q) || (c.with.company || '').toLowerCase().includes(q))
})

function back() {
  chat.activeId = null
}

function close() {
  chat.closeDrawer()
}

async function pick(person: ChatPerson) {
  pickerOpen.value = false
  await chat.openWith(person.id)
}

function submit() {
  const body = draft.value.trim()
  if ((!body && !pending.value.length) || chat.sending || uploading.value) return
  chat.send(body, pending.value)
  draft.value = ''
  pending.value = []
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    submit()
  }
}

watch(() => [chat.activeId, chat.messages.length], async () => {
  await nextTick()
  if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight
})

/** "Jun 14" — the list row date. */
function listDate(iso: string | null): string {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

/** "06:57 PM | May 06" — the thread timestamp. */
function stamp(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const time = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  const day = d.toLocaleDateString('en-US', { month: 'long', day: '2-digit' })
  return `${time} | ${day}`
}

interface Sender { name: string, avatar_url: string | null }
function sender(mine: boolean): Sender {
  if (mine) {
    return { name: chat.profile?.name || 'You', avatar_url: chat.profile?.avatar_url ?? null }
  }
  return { name: chat.active?.with.name || 'Attendee', avatar_url: chat.active?.with.avatar_url ?? null }
}
</script>

<template>
  <Teleport to="body">
    <div class="scrim" @click.self="close">
      <aside class="drawer" role="dialog" aria-label="Chat">
        <!-- ── Conversations list ─────────────────────────────────────── -->
        <template v-if="!inThread">
          <header class="head">
            <span class="htitle">
              <svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z" /></svg>
              Conversations
            </span>
            <button class="hbtn" type="button" title="New chat" aria-label="New chat" @click="pickerOpen = true">
              <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
            </button>
            <button class="hbtn" type="button" title="Search" aria-label="Search" @click="searchOpen = !searchOpen">
              <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
            </button>
            <button class="x" type="button" title="Close" aria-label="Close" @click="close">
              <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
          </header>

          <div v-if="searchOpen" class="searchrow">
            <input v-model="filter" type="search" placeholder="Search conversations…" autofocus>
          </div>

          <div class="list">
            <div v-if="chat.loading && !chat.conversations.length" class="note">Loading conversations…</div>
            <div v-else-if="!shown.length" class="note">
              {{ chat.conversations.length ? 'No matches.' : 'No conversations yet — start one with the pencil above.' }}
            </div>

            <button v-for="c in shown" :key="c.id" type="button" class="row" @click="chat.select(c.id)">
              <span class="av">
                <img v-if="c.with.avatar_url" :src="c.with.avatar_url" :alt="c.with.name">
                <template v-else>{{ initials(c.with.name) }}</template>
              </span>
              <span class="mid">
                <span class="name">{{ c.with.name }}</span>
                <span v-if="c.with.role !== 'attendee'" class="role">{{ c.with.role }}</span>
                <span class="preview">{{ c.last_message ? c.last_message.body : 'Say hello 👋' }}</span>
              </span>
              <span class="side">
                <span class="date">{{ c.last_message ? listDate(c.last_message.created_at) : '' }}</span>
                <span v-if="c.unread" class="badge">{{ c.unread > 99 ? '99+' : c.unread }}</span>
              </span>
            </button>
          </div>
        </template>

        <!-- ── Thread ─────────────────────────────────────────────────── -->
        <template v-else>
          <header class="head">
            <button class="hbtn back" type="button" title="Back" aria-label="Back" @click="back">
              <svg viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7" /></svg>
            </button>
            <span class="tname">{{ chat.active?.with.name }}</span>
            <button class="x" type="button" title="Close" aria-label="Close" @click="close">
              <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
          </header>

          <div ref="scroller" class="msgs">
            <div v-if="chat.messagesLoading" class="note">Loading messages…</div>
            <div v-else-if="!chat.messages.length" class="note">No messages yet — say hello 👋</div>

            <div v-for="m in chat.messages" :key="m.id" class="msg">
              <span class="mav">
                <img v-if="sender(m.mine).avatar_url" :src="sender(m.mine).avatar_url!" :alt="sender(m.mine).name">
                <template v-else>{{ initials(sender(m.mine).name) }}</template>
              </span>
              <div class="mbody">
                <span class="mname">{{ sender(m.mine).name }}</span>
                <span class="mtime">{{ stamp(m.created_at) }}</span>
                <p v-if="m.body">{{ m.body }}</p>
                <ChatAttachments v-if="m.attachments?.length" :attachments="m.attachments" />
              </div>
            </div>
          </div>

          <!-- Pending attachments (before send) -->
          <div v-if="pending.length || uploading" class="pending">
            <span v-for="(a, i) in pending" :key="a.url" class="chip">
              <img v-if="a.kind === 'image'" :src="a.url" alt="">
              <svg v-else viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7zM15 3v4h4" /></svg>
              <span class="cname">{{ a.name || a.kind }}</span>
              <button type="button" title="Remove" @click="pending.splice(i, 1)">×</button>
            </span>
            <span v-if="uploading" class="chip up">Uploading…</span>
          </div>

          <footer class="composer">
            <button class="plus" type="button" title="Attach a file" aria-label="Attach a file" :disabled="uploading || pending.length >= 5" @click="fileInput?.click()">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            </button>
            <input
              v-model="draft"
              type="text"
              placeholder="Type a message"
              @keydown="onKeydown"
            >
            <button class="send" type="button" title="Send" aria-label="Send" :disabled="(!draft.trim() && !pending.length) || chat.sending || uploading" @click="submit">
              <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z" /></svg>
            </button>
            <input ref="fileInput" type="file" multiple :accept="ACCEPT" class="hiddeninput" @change="onFiles">
          </footer>
        </template>
      </aside>
    </div>

    <ChatNewChatModal v-if="pickerOpen" @close="pickerOpen = false" @pick="pick" />
  </Teleport>
</template>

<style scoped>
.scrim { position: fixed; inset: 0; z-index: 70; background: rgba(15,23,42,.18); }
.drawer {
  position: absolute; top: 0; right: 0; height: 100%;
  width: 420px; max-width: 100vw;
  background: #fff; box-shadow: -14px 0 40px rgba(15,23,42,.16);
  display: flex; flex-direction: column; min-height: 0;
  animation: slide .18s ease;
}
@keyframes slide { from { transform: translateX(30px); opacity: .4; } to { transform: none; opacity: 1; } }

/* ── Header (gray band like the reference) ─────────────────────────── */
.head { display: flex; align-items: center; gap: 8px; background: #eceef2; padding: 16px 16px; }
.htitle { flex: 1; display: inline-flex; align-items: center; gap: 10px; color: var(--brand-primary); font-weight: 700; font-size: .98rem; }
.htitle svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.hbtn { border: none; background: none; color: var(--brand-primary); padding: 6px; border-radius: 8px; cursor: pointer; line-height: 0; }
.hbtn:hover { background: rgba(255,255,255,.7); }
.hbtn svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.hbtn.back { color: #64748b; }
.tname { flex: 1; color: #334155; font-weight: 700; font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.x { border: none; background: #e02d2d; color: #fff; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.x svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; }

.searchrow { padding: 10px 16px; border-bottom: 1px solid #eef0f3; }
.searchrow input { width: 100%; border: 1px solid #e2e8f0; border-radius: 999px; padding: 9px 16px; font: inherit; font-size: .86rem; outline: none; color: #334155; }
.searchrow input:focus { border-color: var(--brand-primary); }

/* ── Conversations list ────────────────────────────────────────────── */
.list { flex: 1; overflow-y: auto; min-height: 0; padding: 10px 0; }
.note { color: #94a3b8; font-size: .85rem; text-align: center; padding: 40px 24px; }

.row { display: flex; align-items: flex-start; gap: 14px; width: 100%; border: none; background: none; padding: 16px 22px; cursor: pointer; text-align: left; font: inherit; border-bottom: 1px solid #f1f2f6; }
.row:hover { background: #f8fafc; }
.av { flex: 0 0 auto; width: 52px; height: 52px; border-radius: 50%; background: #eef1f8; color: #8a93a6; font-weight: 700; font-size: .95rem; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #e5e9f2; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.mid { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.name { color: var(--brand-primary); font-weight: 700; font-size: .92rem; }
.role { color: #94a3b8; font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.preview { color: #64748b; font-size: .84rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.side { flex: 0 0 auto; display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
.date { color: #94a3b8; font-size: .74rem; font-weight: 600; }
.badge { min-width: 19px; height: 19px; padding: 0 5px; border-radius: 999px; background: var(--brand-primary); color: #fff; font-size: .68rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }

/* ── Thread ────────────────────────────────────────────────────────── */
.msgs { flex: 1; overflow-y: auto; min-height: 0; padding: 18px 20px; display: flex; flex-direction: column; gap: 20px; }
.msg { display: flex; align-items: flex-start; gap: 12px; }
.mav { flex: 0 0 auto; width: 44px; height: 44px; border-radius: 50%; background: #eef1f8; color: #8a93a6; font-weight: 700; font-size: .82rem; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #e5e9f2; }
.mav img { width: 100%; height: 100%; object-fit: cover; }
.mbody { min-width: 0; display: flex; flex-direction: column; }
.mname { color: var(--brand-primary); font-weight: 700; font-size: .88rem; }
.mtime { color: #94a3b8; font-size: .72rem; font-weight: 600; margin-top: 1px; }
.mbody p { margin: 7px 0 0; color: #475569; font-size: .9rem; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }

/* ── Pending attachments ───────────────────────────────────────────── */
.pending { display: flex; flex-wrap: wrap; gap: 7px; padding: 10px 16px 0; background: #fff; border-top: 1px solid #eef0f3; }
.chip { display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 999px; padding: 4px 6px 4px 5px; font-size: .74rem; color: #475569; max-width: 200px; }
.chip img { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; }
.chip svg { width: 16px; height: 16px; fill: none; stroke: #64748b; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.chip .cname { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chip button { border: none; background: none; color: #94a3b8; cursor: pointer; font-size: .95rem; line-height: 1; padding: 0 3px; }
.chip.up { color: #94a3b8; }

/* ── Composer ──────────────────────────────────────────────────────── */
.composer { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-top: 1px solid #eef0f3; background: #fff; }
.pending + .composer { border-top: none; }
.plus { flex: 0 0 auto; width: 38px; height: 38px; border: none; border-radius: 50%; background: var(--brand-primary); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.plus:disabled { opacity: .5; cursor: default; }
.plus svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; }
.hiddeninput { display: none; }
.composer input { flex: 1; border: 1.5px solid #c7cdf5; border-radius: 12px; padding: 12px 16px; font: inherit; font-size: .92rem; color: #334155; outline: none; }
.composer input::placeholder { color: #a5b0f0; }
.composer input:focus { border-color: var(--brand-primary); }
.send { flex: 0 0 auto; width: 46px; height: 44px; border: none; border-radius: 10px; background: var(--brand-primary); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.send:disabled { opacity: .5; cursor: default; }
.send svg { width: 19px; height: 19px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

@media (max-width: 480px) {
  .drawer { width: 100vw; }
}
</style>
