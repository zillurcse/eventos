<script setup lang="ts">
const store = useLoginSessionsStore()
store.fetch()

const revokingId = ref<number | null>(null)
async function revoke(id: number) {
  if (revokingId.value) return
  revokingId.value = id
  try {
    await store.revoke(id)
  } finally {
    revokingId.value = null
  }
}

const loggingOutAll = ref(false)
async function logoutAll() {
  if (loggingOutAll.value) return
  loggingOutAll.value = true
  try {
    await store.logoutOthers()
  } finally {
    loggingOutAll.value = false
  }
}

const hasOthers = computed(() => store.sessions.some(s => !s.is_current))
</script>

<template>
  <div class="sessions">
    <div class="head">
      <div>
        <h2>Sessions</h2>
        <p>
          Manage and logout your active sessions on other browsers and devices. If necessary, you may logout of all of
          your other browser sessions across all of your devices. If you feel your account has been compromised, you
          should also update your password.
        </p>
      </div>
      <button v-if="hasOthers" type="button" class="logout-all" :disabled="loggingOutAll" @click="logoutAll">
        {{ loggingOutAll ? 'Logging out…' : 'Logout from all sessions' }}
      </button>
    </div>

    <div class="divider" />

    <h3>Active Sessions</h3>

    <p v-if="store.loading && !store.loaded" class="loading">Loading…</p>

    <ul v-else class="list">
      <li v-for="s in store.sessions" :key="s.id" class="row">
        <span class="icon">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="13" rx="2" /><path d="M8 21h8M12 17v4" /></svg>
        </span>

        <div class="info">
          <div class="line1">
            <strong>{{ s.device }}</strong>
            <span v-if="s.is_current" class="badge">Current Session</span>
          </div>
          <small>Last active : {{ timeAgo(s.last_active_at) }}</small>
        </div>

        <div class="right">
          <div v-if="s.ip_address" class="ip">
            <strong>{{ s.ip_address }}</strong>
            <small>IP Address</small>
          </div>
          <button
            v-if="!s.is_current" type="button" class="logout-one"
            :disabled="revokingId === s.id" @click="revoke(s.id)"
          >
            {{ revokingId === s.id ? 'Logging out…' : 'Logout' }}
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.sessions { display: flex; flex-direction: column; }
.loading { color: #94a3b8; font-size: .9rem; }

.head { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; }
.head h2 { margin: 0 0 8px; font-size: 1.05rem; font-weight: 800; color: #1e293b; }
.head p { margin: 0; max-width: 640px; color: #64748b; font-size: .86rem; line-height: 1.55; }

.logout-all {
  flex: 0 0 auto; border: none; background: #fef2f2; color: #ef4444; border-radius: 8px;
  padding: 9px 16px; font: inherit; font-size: .85rem; font-weight: 700; cursor: pointer; white-space: nowrap;
}
.logout-all:hover:not(:disabled) { background: #fee2e2; }
.logout-all:disabled { opacity: .6; cursor: default; }

.divider { height: 1px; background: #eef0f3; margin: 20px 0; }

.list h3, h3 { margin: 0 0 16px; font-size: .95rem; font-weight: 700; color: #1e293b; }

.list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.row { display: flex; align-items: center; gap: 14px; padding: 16px 0; border-bottom: 1px solid #f1f2f6; }
.row:last-child { border-bottom: none; }

.icon {
  flex: 0 0 auto; width: 40px; height: 40px; border-radius: 10px; background: #f7f8fa;
  display: flex; align-items: center; justify-content: center;
}
.icon svg { width: 20px; height: 20px; fill: none; stroke: #64748b; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

.info { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.line1 { display: flex; align-items: center; gap: 10px; }
.line1 strong { color: #1e293b; font-size: .92rem; font-weight: 700; }
.info small { color: #94a3b8; font-size: .78rem; }

.badge {
  background: #dcfce7; color: #16a34a; font-size: .72rem; font-weight: 700; border-radius: 999px; padding: 2px 10px;
}

.right { flex: 0 0 auto; display: flex; align-items: center; gap: 20px; }
.ip { display: flex; flex-direction: column; gap: 4px; text-align: right; }
.ip strong { color: #1e293b; font-size: .88rem; font-weight: 700; }
.ip small { color: #94a3b8; font-size: .78rem; }

.logout-one {
  border: none; background: #fef2f2; color: #ef4444; border-radius: 8px;
  padding: 8px 16px; font: inherit; font-size: .82rem; font-weight: 700; cursor: pointer; white-space: nowrap;
}
.logout-one:hover:not(:disabled) { background: #fee2e2; }
.logout-one:disabled { opacity: .6; cursor: default; }

@media (max-width: 640px) {
  .head { flex-direction: column; }
  .row { flex-wrap: wrap; }
  .right { width: 100%; justify-content: space-between; }
}
</style>
