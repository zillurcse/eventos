<script setup lang="ts">
/**
 * "My Briefcase" section of the Profile page: saved Files & Documents (the
 * same drawer opened from the header briefcase icon, shown here inline) plus
 * the new Notes sub-tab (jotted from Speakers/Sessions/Delegates cards).
 */
const briefcase = useBriefcaseStore()

type Tab = 'files' | 'notes'
const tab = ref<Tab>('files')

onMounted(() => {
  if (!briefcase.loaded && !briefcase.loading) briefcase.fetch()
})

function kindLabel(kind: string) {
  return ({ pdf: 'PDF FILE', doc: 'DOC FILE', excel: 'EXCEL FILE', image: 'IMAGE' } as Record<string, string>)[kind] || 'FILE'
}
function fileName(item: { title: string, url: string }) {
  return item.title || item.url.split('/').pop() || 'File'
}
</script>

<template>
  <div class="briefcase-tab">
    <nav class="tabs">
      <button type="button" class="tab" :class="{ on: tab === 'files' }" @click="tab = 'files'">Files &amp; Documents</button>
      <button type="button" class="tab" :class="{ on: tab === 'notes' }" @click="tab = 'notes'">Notes</button>
    </nav>

    <template v-if="tab === 'files'">
      <p v-if="briefcase.loading && !briefcase.items.length" class="state">Loading…</p>
      <p v-else-if="!briefcase.items.length" class="state">No files yet. Add brochures from exhibitor pages.</p>

      <div v-else class="files">
        <div v-for="it in briefcase.items" :key="it.id" class="file-row">
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
            <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
          </button>
        </div>
      </div>
    </template>

    <ProfileNotesTab v-else />
  </div>
</template>

<style scoped>
.tabs { display: flex; gap: 6px; border-bottom: 1px solid #eef0f3; margin-bottom: 24px; }
.tab { border: none; background: none; padding: 10px 6px; margin-right: 18px; font: inherit; font-size: .9rem; font-weight: 600; color: #94a3b8; cursor: pointer; border-bottom: 2px solid transparent; }
.tab:hover { color: #475569; }
.tab.on { color: var(--brand-primary); border-bottom-color: var(--brand-primary); font-weight: 700; }

.state { color: #94a3b8; font-size: .9rem; padding: 24px 0; text-align: center; }

.files { display: flex; flex-direction: column; gap: 12px; }
.file-row { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #eef0f3; border-radius: 12px; padding: 14px 16px; }
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
