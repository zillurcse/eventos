<script setup lang="ts">
import type { Meeting } from '~/stores/meetings'

defineProps<{ today: number, upcoming: number, current: Meeting | null, joining: boolean }>()
defineEmits<{ join: [] }>()
</script>

<template>
  <div class="mw">
    <header class="mw-head">
      <h2>Meetings</h2>
      <NuxtLink to="/meetings" class="viewall">View all
        <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </NuxtLink>
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

    <!-- A confirmed meeting whose window is open right now — join directly. -->
    <div v-if="current" class="current">
      <span class="cav"><UserAvatar :src="current.counterpart?.avatar_url" :name="current.counterpart?.name" /></span>
      <div class="cmeta">
        <strong>{{ current.counterpart?.name || 'Attendee' }}</strong>
        <small>Meeting is live now</small>
      </div>
      <button type="button" class="join" :disabled="joining" @click="$emit('join')">
        {{ joining ? 'Joining…' : 'Join' }}
      </button>
    </div>

    <div v-else-if="!today && !upcoming" class="empty">
      <svg viewBox="0 0 24 24"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 20a6 6 0 0 1 12 0M17 11a3 3 0 1 0 0-6M15 14a6 6 0 0 1 7 6" /></svg>
      <p>No current meetings.</p>
    </div>
  </div>
</template>

<style scoped>
.mw { background: var(--brand-content-bg, #fff); border-radius: 16px; padding: 16px 16px 20px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
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

.current { margin-top: 16px; padding-top: 16px; border-top: 1px solid #eef0f3; display: flex; align-items: center; gap: 10px; }
.cav { width: 38px; height: 38px; border-radius: 50%; flex: 0 0 auto; overflow: hidden; }
.cmeta { min-width: 0; flex: 1; }
.cmeta strong { display: block; font-size: .86rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cmeta small { color: #16a34a; font-size: .74rem; font-weight: 600; }
.join { flex: 0 0 auto; border: none; border-radius: 9px; padding: 8px 14px; background: var(--brand-primary); color: #fff; font: inherit; font-size: .82rem; font-weight: 700; cursor: pointer; }
.join:disabled { opacity: .6; cursor: default; }

:global(html[data-theme="minimal"]) .mw {
  border-radius: var(--theme-radius, 8px);
  padding: 14px;
  box-shadow: none;
  border: 1px solid var(--line, #e2e8f0);
}
:global(html[data-theme="minimal"]) .mw-head { margin-bottom: 10px; }
:global(html[data-theme="minimal"]) .mw-head h2 {
  font-size: .78rem;
  letter-spacing: .3px;
}
:global(html[data-theme="minimal"]) .ic {
  width: 34px;
  height: 34px;
}
:global(html[data-theme="minimal"]) .counter strong {
  font-size: 1.1rem;
}
:global(html[data-theme="minimal"]) .join {
  border-radius: 6px;
  padding: 6px 12px;
}

:global(html[data-theme="modern"]) .mw {
  border-radius: var(--theme-radius, 18px);
  padding: 22px;
  box-shadow: var(--theme-shadow, 0 8px 28px rgba(15, 23, 42, 0.06));
  height: 100%;
}
:global(html[data-theme="modern"]) .mw-head { margin-bottom: 18px; }
:global(html[data-theme="modern"]) .mw-head h2 {
  font-size: .95rem;
  letter-spacing: .2px;
  text-transform: none;
  font-weight: 700;
}
:global(html[data-theme="modern"]) .ic {
  width: 46px;
  height: 46px;
  border-radius: 14px;
}
:global(html[data-theme="modern"]) .counter strong {
  font-size: 1.45rem;
}
:global(html[data-theme="modern"]) .join {
  border-radius: 12px;
  padding: 10px 18px;
}
</style>
