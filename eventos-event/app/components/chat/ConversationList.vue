<script setup lang="ts">
import type { ChatConversationItem } from '~/stores/chat'

const props = defineProps<{
  conversations: ChatConversationItem[]
  activeId: string | null
  loading?: boolean
}>()

defineEmits<{
  (e: 'select', id: string): void
  (e: 'new'): void
}>()

const filter = ref('')

const shown = computed(() => {
  const q = filter.value.trim().toLowerCase()
  if (!q) return props.conversations
  return props.conversations.filter(c =>
    c.with.name.toLowerCase().includes(q) || (c.with.company || '').toLowerCase().includes(q))
})

const ROLE_CLS: Record<string, string> = { speaker: 'speaker', exhibitor: 'exhibitor', sponsor: 'sponsor' }
</script>

<template>
  <aside class="pane">
    <header class="head">
      <h2>Messaging</h2>
      <button class="new" type="button" title="New chat" @click="$emit('new')">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        New chat
      </button>
    </header>

    <div class="search">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
      <input v-model="filter" type="search" placeholder="Search conversations…">
    </div>

    <div class="list">
      <div v-if="loading && !conversations.length" class="note">Loading conversations…</div>
      <div v-else-if="!shown.length" class="note">
        {{ conversations.length ? 'No matches.' : 'No conversations yet — start one with New chat.' }}
      </div>

      <button
        v-for="c in shown" :key="c.id"
        type="button"
        class="item"
        :class="{ active: c.id === activeId }"
        @click="$emit('select', c.id)"
      >
        <span class="av">
          <img v-if="c.with.avatar_url" :src="c.with.avatar_url" :alt="c.with.name">
          <template v-else>{{ initials(c.with.name) }}</template>
        </span>
        <span class="mid">
          <span class="top">
            <span class="name">{{ c.with.name }}</span>
            <span v-if="ROLE_CLS[c.with.role]" class="role" :class="ROLE_CLS[c.with.role]">{{ c.with.role }}</span>
          </span>
          <span class="preview" :class="{ unread: c.unread }">
            <template v-if="c.last_message">{{ c.last_message.mine ? 'You: ' : '' }}{{ c.last_message.body }}</template>
            <template v-else>Say hello 👋</template>
          </span>
        </span>
        <span class="side">
          <span class="time">{{ c.last_message ? timeAgo(c.last_message.created_at) : '' }}</span>
          <span v-if="c.unread" class="badge">{{ c.unread > 99 ? '99+' : c.unread }}</span>
        </span>
      </button>
    </div>
  </aside>
</template>

<style scoped>
.pane { display: flex; flex-direction: column; min-height: 0; background: #fff; border-right: 1px solid #eef0f3; }

.head { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 10px; }
.head h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #1e293b; }
.new { display: inline-flex; align-items: center; gap: 6px; border: none; background: var(--brand-primary); color: #fff; font: inherit; font-size: .78rem; font-weight: 700; border-radius: 999px; padding: 7px 14px; cursor: pointer; }
.new svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; }

.search { position: relative; padding: 0 16px 10px; }
.search svg { position: absolute; left: 27px; top: 10px; width: 15px; height: 15px; fill: none; stroke: #94a3b8; stroke-width: 1.8; stroke-linecap: round; }
.search input { width: 100%; border: 1px solid #e2e8f0; border-radius: 999px; padding: 8px 14px 8px 34px; font: inherit; font-size: .84rem; outline: none; color: #334155; background: #f8fafc; }
.search input:focus { border-color: var(--brand-primary); background: #fff; }

.list { flex: 1; overflow-y: auto; min-height: 0; }
.note { color: #94a3b8; font-size: .84rem; text-align: center; padding: 34px 20px; }

.item { display: flex; align-items: center; gap: 11px; width: 100%; border: none; background: none; padding: 11px 16px; cursor: pointer; text-align: left; font: inherit; border-left: 3px solid transparent; }
.item:hover { background: #f8fafc; }
.item.active { background: color-mix(in srgb, var(--brand-primary) 7%, #fff); border-left-color: var(--brand-primary); }

.av { flex: 0 0 auto; width: 44px; height: 44px; border-radius: 50%; background: var(--brand-primary); color: #fff; font-weight: 700; font-size: .85rem; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.av img { width: 100%; height: 100%; object-fit: cover; }

.mid { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.top { display: flex; align-items: center; gap: 6px; min-width: 0; }
.name { font-weight: 700; color: #1e293b; font-size: .88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.role { flex: 0 0 auto; font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; border-radius: 999px; padding: 2px 7px; }
.role.speaker { color: #7c3aed; background: #ede9fe; }
.role.exhibitor { color: #0f766e; background: #ccfbf1; }
.role.sponsor { color: #b45309; background: #fef3c7; }
.preview { color: #94a3b8; font-size: .8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.preview.unread { color: #334155; font-weight: 600; }

.side { flex: 0 0 auto; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.time { color: #94a3b8; font-size: .7rem; }
.badge { min-width: 19px; height: 19px; padding: 0 5px; border-radius: 999px; background: var(--brand-primary); color: #fff; font-size: .68rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
</style>
