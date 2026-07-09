<script setup lang="ts">
const briefcase = useBriefcaseStore()

function kindLabel(kind: string) {
  return ({ pdf: 'PDF FILE', doc: 'DOC FILE', excel: 'EXCEL FILE', image: 'IMAGE' } as Record<string, string>)[kind] || 'FILE'
}
function fileName(item: { title: string, url: string }) {
  return item.title || item.url.split('/').pop() || 'File'
}

function downloadAll() {
  for (const it of briefcase.items) window.open(it.url, '_blank', 'noopener')
}
</script>

<template>
  <Teleport to="body">
    <div class="scrim" @click.self="briefcase.closeDrawer()">
      <aside class="drawer" role="dialog" aria-label="Briefcase">
        <header class="head">
          <span class="htitle">
            <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /></svg>
            Briefcase
          </span>
          <button v-if="briefcase.items.length" class="dlall" type="button" title="Download all" aria-label="Download all" @click="downloadAll">
            <svg viewBox="0 0 24 24"><path d="M12 3v12M7 12l5 5 5-5M5 21h14" /></svg>
          </button>
          <button class="x" type="button" title="Close" aria-label="Close" @click="briefcase.closeDrawer()">
            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </header>

        <div class="list">
          <div v-if="briefcase.loading && !briefcase.items.length" class="note">Loading…</div>
          <div v-else-if="!briefcase.items.length" class="note">
            No files yet. Add brochures from exhibitor pages.
          </div>

          <div v-for="it in briefcase.items" :key="it.id" class="row">
            <span class="fic" :class="it.kind">
              <svg viewBox="0 0 24 24"><path d="M14 3v5h5M14 3H6v18h12V8z" /></svg>
            </span>
            <span class="mid">
              <a :href="it.url" target="_blank" rel="noopener" class="fname">{{ fileName(it) }}</a>
              <span class="ftype">{{ kindLabel(it.kind) }}</span>
            </span>
            <a :href="it.url" target="_blank" rel="noopener" download class="act dl" title="Download">
              <svg viewBox="0 0 24 24"><path d="M12 3v12M7 12l5 5 5-5M5 21h14" /></svg>
            </a>
            <button class="act del" type="button" title="Remove" @click="briefcase.remove(it.id)">
              <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><path d="M10 11v6M14 11v6" /></svg>
            </button>
          </div>
        </div>
      </aside>
    </div>
  </Teleport>
</template>

<style scoped>
.scrim { position: fixed; inset: 0; z-index: 70; background: rgba(15,23,42,.18); }
.drawer { position: absolute; top: 0; right: 0; height: 100%; width: 420px; max-width: 100vw; background: #f4f5f8; box-shadow: -14px 0 40px rgba(15,23,42,.16); display: flex; flex-direction: column; min-height: 0; animation: slide .18s ease; }
@keyframes slide { from { transform: translateX(30px); opacity: .4; } to { transform: none; opacity: 1; } }

.head { display: flex; align-items: center; gap: 10px; background: #eceef2; padding: 16px; }
.htitle { flex: 1; display: inline-flex; align-items: center; gap: 10px; color: #475569; font-weight: 700; font-size: 1rem; }
.htitle svg { width: 22px; height: 22px; fill: none; stroke: #475569; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.dlall { width: 40px; height: 40px; border-radius: 50%; border: none; background: var(--brand-primary); color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.dlall svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.x { width: 34px; height: 34px; border-radius: 50%; border: none; background: #e02d2d; color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.x svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; }

.list { flex: 1; overflow-y: auto; min-height: 0; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.note { color: #94a3b8; font-size: .88rem; text-align: center; padding: 44px 24px; }

.row { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #eef0f3; border-radius: 12px; padding: 14px 16px; }
.fic { width: 40px; height: 40px; border-radius: 8px; border: 1px solid #e5e9f2; display: inline-flex; align-items: center; justify-content: center; color: #64748b; flex: 0 0 auto; }
.fic.pdf { color: #dc2626; }
.fic svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.mid { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.fname { color: var(--brand-primary); font-weight: 700; font-size: .86rem; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fname:hover { text-decoration: underline; }
.ftype { color: #94a3b8; font-size: .72rem; font-weight: 600; letter-spacing: .3px; }
.act { width: 38px; height: 38px; border-radius: 50%; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; text-decoration: none; }
.act svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.act.dl { background: var(--brand-primary); color: #fff; }
.act.del { background: #e02d2d; color: #fff; }
</style>
