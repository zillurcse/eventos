<script setup lang="ts">
// The event navigation. Only Reception is wired today; the rest render as
// "coming soon" placeholders (no dead routes) per the current build scope.
interface Tab { key: string, label: string, to?: string, icon: string }

const tabs: Tab[] = [
  { key: 'reception', label: 'Reception', to: '/reception', icon: 'M4 20v-8l8-6 8 6v8h-6v-6h-4v6z' },
  { key: 'feed', label: 'Event Feed', to: '/feed', icon: 'M4 5h16M4 12h16M4 19h10' },
  { key: 'sessions', label: 'Sessions', to: '/sessions', icon: 'M5 4h14v16l-7-3-7 3z' },
  { key: 'speakers', label: 'Speakers', to: '/speakers', icon: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM5 20a7 7 0 0 1 14 0' },
  { key: 'delegates', label: 'Delegates', to: '/delegates', icon: 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 19a6 6 0 0 1 12 0M17 11a3 3 0 1 0 0-6M15 13a6 6 0 0 1 7 6' },
  { key: 'meetings', label: 'Meetings', to: '/meetings', icon: 'M7 4v3M17 4v3M4 9h16M5 7h14v13H5z' },
  { key: 'lounge', label: 'Lounge', icon: 'M4 12v-2a3 3 0 0 1 6 0v2h4v-2a3 3 0 0 1 6 0v2M3 12h18v6H3zM6 18v2M18 18v2' },
  { key: 'expolens', label: 'ExpoLens', icon: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM4 7h3l1-2h8l1 2h3v12H4z' },
  { key: 'rooms', label: 'Rooms', to: '/rooms', icon: 'M4 20V6l8-3 8 3v14M4 20h16M9 20v-5h6v5' },
  { key: 'exhibitors', label: 'Exhibitors', to: '/exhibitors', icon: 'M4 9l1-4h14l1 4M4 9v11h16V9M4 9h16M9 20v-6h6v6' },
  { key: 'contests', label: 'Contests', icon: 'M7 4h10v3a5 5 0 0 1-10 0zM7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3M9 15h6l-1 5h-4z' },
  { key: 'badges', label: 'My Badges', icon: 'M6 3h12v10l-6 4-6-4zM9 20h6' },
]

const route = useRoute()
</script>

<template>
  <nav class="eventnav" aria-label="Event sections">
    <div class="track">
      <template v-for="t in tabs" :key="t.key">
        <NuxtLink
          v-if="t.to"
          :to="t.to"
          class="tab"
          :class="{ active: route.path === t.to }"
        >
          <svg viewBox="0 0 24 24"><path :d="t.icon" /></svg>
          <span>{{ t.label }}</span>
        </NuxtLink>
        <button v-else type="button" class="tab disabled" title="Coming soon" disabled>
          <svg viewBox="0 0 24 24"><path :d="t.icon" /></svg>
          <span>{{ t.label }}</span>
        </button>
      </template>
    </div>
  </nav>
</template>

<style scoped>
.eventnav { background: #fff; border-bottom: 1px solid #e6e8ec; box-shadow: 0 1px 2px rgba(15,23,42,.03); }
.track { display: flex; align-items: stretch; gap: 4px; max-width: 1180px; margin: 0 auto; padding: 0 10px; overflow-x: auto; scrollbar-width: thin; }
.track::-webkit-scrollbar { height: 4px; }
.track::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

.tab {
  flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; gap: 5px;
  padding: 12px 14px 10px; min-width: 74px; border: none; background: none; cursor: pointer;
  color: #94a3b8; font: inherit; font-size: .66rem; font-weight: 600; letter-spacing: .3px;
  text-transform: uppercase; border-bottom: 3px solid transparent; white-space: nowrap;
}
.tab svg { width: 24px; height: 24px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.tab:not(.disabled):hover { color: var(--brand-primary); }
.tab.active { color: var(--brand-primary); border-bottom-color: var(--brand-primary); }
.tab.disabled { cursor: default; opacity: .85; }
</style>
