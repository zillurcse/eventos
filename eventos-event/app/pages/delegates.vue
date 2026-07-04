<script setup lang="ts">
import type { Delegate } from '~/stores/delegates'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useDelegatesStore()

const search = ref('')
const sort = ref<'default' | 'az' | 'za'>('default')

onMounted(() => { if (!store.loaded) store.fetchDelegates() })

const sortOptions: Array<{ key: 'az' | 'za' | 'default', label: string }> = [
  { key: 'az', label: 'By A to Z' },
  { key: 'za', label: 'By Z to A' },
  { key: 'default', label: 'Default' },
]

const filtered = computed<Delegate[]>(() => {
  const q = search.value.trim().toLowerCase()
  let list = store.delegates.filter((d) => {
    if (!q) return true
    return `${d.name} ${d.job_title} ${d.company}`.toLowerCase().includes(q)
  })

  if (sort.value !== 'default') {
    list = [...list].sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    if (sort.value === 'za') list.reverse()
  }
  return list
})
</script>

<template>
  <div class="grid">
    <!-- Left rail: search + sort -->
    <aside class="rail">
      <div class="search">
        <input v-model="search" type="text" placeholder="Search">
        <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" /></svg>
      </div>

      <div class="card">
        <div class="ct">
          <span>Sort By</span>
          <svg viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
        </div>
        <div class="opts">
          <button
            v-for="o in sortOptions"
            :key="o.key"
            type="button"
            class="opt"
            :class="{ on: sort === o.key }"
            @click="sort = o.key"
          >
            {{ o.label }}
            <svg v-if="sort === o.key" class="chk" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- Delegate grid -->
    <section class="main">
      <div class="head">
        <h1>Delegates</h1>
        <p class="sub">Attendees you can meet — connect and save the people you want to reach.</p>
      </div>

      <div v-if="store.loading && !store.loaded" class="state">Loading delegates…</div>
      <div v-else-if="store.error" class="state">Couldn’t load delegates. Please try again.</div>
      <div v-else-if="!filtered.length" class="state">No delegates match your search.</div>

      <div v-else class="cards">
        <DelegatesCard v-for="d in filtered" :key="d.id" :delegate="d" />
      </div>
    </section>
  </div>
</template>

<style scoped>
.grid { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
@media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }

.rail { display: flex; flex-direction: column; gap: 16px; }
.search { position: relative; }
.search input { width: 100%; border: none; background: #fff; border-radius: 12px; padding: 14px 46px 14px 18px; font: inherit; font-size: .95rem; color: #334155; outline: none; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.search input::placeholder { color: #94a3b8; }
.search input:focus { box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand-primary) 40%, transparent); }
.search svg { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; fill: none; stroke: var(--brand-primary); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

.card { background: #fff; border-radius: 14px; padding: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.ct { display: flex; align-items: center; justify-content: space-between; padding: 4px 6px 12px; border-bottom: 1px solid #eef0f3; color: #334155; font-weight: 600; }
.ct svg { width: 18px; height: 18px; fill: none; stroke: var(--brand-primary); stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.opts { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
.opt { display: flex; align-items: center; justify-content: space-between; border: 1px solid transparent; background: none; border-radius: 10px; padding: 11px 12px; cursor: pointer; font: inherit; font-size: .9rem; color: #475569; text-align: left; }
.opt:hover { background: #f7f8fa; }
.opt.on { background: color-mix(in srgb, var(--brand-primary) 10%, #fff); border-color: color-mix(in srgb, var(--brand-primary) 35%, #fff); color: var(--brand-primary); font-weight: 600; }
.chk { width: 17px; height: 17px; fill: none; stroke: var(--brand-primary); stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

.main { min-width: 0; }
.head { margin-bottom: 16px; }
.head h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }
.sub { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
.state { background: #fff; border-radius: 14px; padding: 48px 0; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
</style>
