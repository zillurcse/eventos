<script setup lang="ts">
definePageMeta({ middleware: 'platform', title: 'Platform overview', subtitle: 'Platform metrics' })

const api = useApi()
const m = ref<any>(null)

onMounted(async () => {
  try { m.value = (await api<any>('/admin/metrics')).data } catch { /* */ }
})

function money(cents: number): string {
  return '$' + ((cents || 0) / 100).toLocaleString(undefined, { minimumFractionDigits: 0 })
}
</script>

<template>
  <div>
    <div v-if="m" class="stats">
      <div class="stat"><div class="n">{{ m.organizations }}</div><div class="l">Organizations</div></div>
      <div class="stat"><div class="n">{{ m.active_subscriptions }}</div><div class="l">Active subs</div></div>
      <div class="stat"><div class="n">{{ money(m.revenue_cents) }}</div><div class="l">Revenue (paid)</div></div>
      <div class="stat"><div class="n">{{ m.events }}</div><div class="l">Events</div></div>
      <div class="stat"><div class="n">{{ m.attendees }}</div><div class="l">Attendees</div></div>
    </div>

    <div v-if="m" class="card">
      <h2>Organizations by status</h2>
      <span v-for="(c, s) in m.organizations_by_status" :key="s" class="badge mr-2" :class="s">
        {{ s }}: {{ c }}
      </span>
    </div>

    <div v-if="m?.recent_audit?.length" class="card">
      <h2>Recent activity</h2>
      <div v-for="(a, i) in m.recent_audit" :key="i" class="muted text-[.9rem] py-[3px]">
        {{ a.event }} · {{ a.entity }} · {{ a.at }}
      </div>
    </div>

    <p v-if="!m" class="muted">Loading platform metrics…</p>
  </div>
</template>
