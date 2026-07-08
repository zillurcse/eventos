<script setup lang="ts">
/**
 * The bell dropdown: latest in-app notifications with unread accents, per-item
 * mark-read on click, and "Mark all as read". Data comes from the
 * notifications store (poll + focus refresh); the parent owns open/close.
 */
const emit = defineEmits<{ (e: 'close'): void }>()

const store = useNotificationsStore()
</script>

<template>
  <div class="panel" role="dialog" aria-label="Notifications">
    <header class="head">
      <h3>Notifications</h3>
      <button
        v-if="store.unread"
        class="readall"
        type="button"
        @click="store.readAll()"
      >Mark all as read</button>
    </header>

    <div class="list">
      <div v-if="store.loading && !store.loaded" class="note">Loading…</div>
      <div v-else-if="!store.items.length" class="note">
        <svg viewBox="0 0 24 24"><path d="M6 16V10a6 6 0 0 1 12 0v6l2 2H4zM10 21h4" /></svg>
        You're all caught up.
      </div>

      <button
        v-for="n in store.items" :key="n.id"
        type="button"
        class="item"
        :class="{ unread: !n.read_at }"
        @click="store.markRead(n)"
      >
        <span class="dot" aria-hidden="true" />
        <span class="content">
          <span class="title">{{ n.title }}</span>
          <span v-if="n.body" class="body">{{ n.body }}</span>
          <span class="time">{{ timeAgo(n.created_at) }}</span>
        </span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.panel { position: absolute; right: 0; top: calc(100% + 8px); width: 360px; max-width: 92vw; background: #fff; border: 1px solid #e6e8ec; border-radius: 14px; box-shadow: 0 16px 40px rgba(15,23,42,.16); overflow: hidden; z-index: 50; }

.head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #f1f2f6; }
.head h3 { margin: 0; font-size: .92rem; font-weight: 800; color: #1e293b; }
.readall { border: none; background: none; color: var(--brand-primary); font: inherit; font-size: .76rem; font-weight: 700; cursor: pointer; }

.list { max-height: 420px; overflow-y: auto; }
.note { display: flex; flex-direction: column; align-items: center; gap: 8px; color: #94a3b8; font-size: .84rem; text-align: center; padding: 34px 20px; }
.note svg { width: 30px; height: 30px; fill: none; stroke: #cbd5e1; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }

.item { display: flex; gap: 10px; width: 100%; border: none; background: none; padding: 12px 16px; cursor: pointer; text-align: left; font: inherit; border-bottom: 1px solid #f7f8fa; }
.item:hover { background: #f8fafc; }
.dot { flex: 0 0 auto; width: 8px; height: 8px; border-radius: 50%; background: transparent; margin-top: 6px; }
.item.unread .dot { background: var(--brand-primary); }
.content { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.title { color: #1e293b; font-size: .84rem; font-weight: 600; }
.item.unread .title { font-weight: 800; }
.body { color: #64748b; font-size: .78rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.time { color: #94a3b8; font-size: .7rem; margin-top: 2px; }
</style>
