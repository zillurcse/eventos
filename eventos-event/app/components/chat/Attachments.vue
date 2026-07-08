<script setup lang="ts">
import type { ChatAttachment } from '~/stores/chat'

/**
 * Renders a message's attachments: images inline (click to open), videos with
 * controls, and everything else (pdf / word / excel / generic) as a file card
 * that opens in a new tab. Shared by the chat drawer and the /chat page.
 */
defineProps<{ attachments: ChatAttachment[] }>()

const FILE_META: Record<string, { label: string, cls: string }> = {
  pdf: { label: 'PDF', cls: 'pdf' },
  doc: { label: 'DOC', cls: 'doc' },
  excel: { label: 'XLS', cls: 'excel' },
  file: { label: 'FILE', cls: 'file' },
}
</script>

<template>
  <div class="atts">
    <template v-for="(a, i) in attachments" :key="i">
      <a v-if="a.kind === 'image'" :href="a.url" target="_blank" rel="noopener" class="img">
        <img :src="a.url" :alt="a.name || 'image'">
      </a>

      <video v-else-if="a.kind === 'video'" :src="a.url" controls preload="metadata" class="vid" />

      <a v-else :href="a.url" target="_blank" rel="noopener" class="filecard">
        <span class="ficon" :class="FILE_META[a.kind]?.cls || 'file'">
          <svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7zM15 3v4h4" /></svg>
          <b>{{ FILE_META[a.kind]?.label || 'FILE' }}</b>
        </span>
        <span class="fname">{{ a.name || 'Open file' }}</span>
        <svg class="dl" viewBox="0 0 24 24"><path d="M12 4v12M6 12l6 6 6-6M5 20h14" /></svg>
      </a>
    </template>
  </div>
</template>

<style scoped>
.atts { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; max-width: 320px; }

.img { display: block; border-radius: 10px; overflow: hidden; border: 1px solid #eef0f3; background: #f4f5f8; }
.img img { display: block; width: 100%; max-height: 260px; object-fit: cover; }

.vid { width: 100%; max-height: 260px; border-radius: 10px; background: #000; }

.filecard { display: flex; align-items: center; gap: 10px; border: 1px solid #e5e9f2; border-radius: 10px; padding: 9px 12px; background: #f8fafc; text-decoration: none; }
.filecard:hover { background: #f1f5f9; }
.ficon { position: relative; flex: 0 0 auto; display: inline-flex; flex-direction: column; align-items: center; }
.ficon svg { width: 26px; height: 26px; fill: none; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.ficon b { font-size: .5rem; font-weight: 800; letter-spacing: .4px; margin-top: -2px; }
.ficon.pdf svg, .ficon.pdf b { stroke: #ef4444; color: #ef4444; }
.ficon.doc svg, .ficon.doc b { stroke: #2563eb; color: #2563eb; }
.ficon.excel svg, .ficon.excel b { stroke: #16a34a; color: #16a34a; }
.ficon.file svg, .ficon.file b { stroke: #64748b; color: #64748b; }
.fname { flex: 1; min-width: 0; color: #334155; font-size: .82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dl { flex: 0 0 auto; width: 15px; height: 15px; fill: none; stroke: #94a3b8; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
</style>
