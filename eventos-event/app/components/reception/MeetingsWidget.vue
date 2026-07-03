<script setup lang="ts">
defineProps<{ today: number, upcoming: number, hasCurrent: boolean }>()
</script>

<template>
  <div class="mw">
    <header class="mw-head">
      <h2>Meetings</h2>
      <a href="#" class="viewall" @click.prevent>View all
        <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </a>
    </header>

    <div class="counters">
      <div class="counter">
        <span class="ic today"><svg viewBox="0 0 24 24"><path d="M7 4v3M17 4v3M4 9h16M5 7h14v13H5z" /></svg></span>
        <div><strong>{{ today }}</strong><small>Today</small></div>
      </div>
      <div class="counter">
        <span class="ic up"><svg viewBox="0 0 24 24"><path d="M7 4v3M17 4v3M4 9h16M5 7h14v13H5zM9 14l2 2 4-4" /></svg></span>
        <div><strong>{{ upcoming }}</strong><small>Upcoming</small></div>
      </div>
    </div>

    <div v-if="!hasCurrent" class="empty">
      <svg viewBox="0 0 24 24"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 20a6 6 0 0 1 12 0M17 11a3 3 0 1 0 0-6M15 14a6 6 0 0 1 7 6" /></svg>
      <p>No current meetings.</p>
    </div>
  </div>
</template>

<style scoped>
.mw { background: #fff; border-radius: 16px; padding: 16px 16px 20px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.mw-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.mw-head h2 { margin: 0; font-size: .82rem; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #334155; }
.viewall { display: inline-flex; align-items: center; gap: 2px; font-size: .74rem; font-weight: 600; color: var(--brand-primary); }
.viewall svg { width: 13px; height: 13px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }

.counters { display: flex; gap: 10px; }
.counter { flex: 1; display: flex; align-items: center; gap: 10px; }
.ic { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.ic svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.ic.today { background: #fff3e0; color: #f59e0b; }
.ic.up { background: #e6f9f0; color: #10b981; }
.counter strong { display: block; font-size: 1.3rem; font-weight: 800; color: #1e293b; line-height: 1; }
.counter small { color: #94a3b8; font-size: .78rem; }

.empty { margin-top: 18px; padding: 20px 0 6px; display: flex; flex-direction: column; align-items: center; gap: 10px; color: #cbd5e1; }
.empty svg { width: 46px; height: 46px; fill: none; stroke: currentColor; stroke-width: 1.4; stroke-linecap: round; stroke-linejoin: round; }
.empty p { margin: 0; color: #94a3b8; font-size: .82rem; }
</style>
