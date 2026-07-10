<script setup lang="ts">
const { docForm, subSaving, subError, documents, addDocument, removeDocument } = useExhibitorContext()

const columns = [
  { key: 'title', label: 'Title' },
  { key: 'url', label: 'Link' },
]
</script>

<template>
  <div>
    <!-- Add document form -->
    <div class="border border-line rounded-xl p-4 mb-5 bg-[#f7f8fa]">
      <p class="font-semibold text-[.92rem] m-0 mb-3 text-ink">Add a document</p>
      <div class="grid gap-2" >
        <AppInput v-model="docForm.title" label="Title" placeholder="Document title" />
        <AppInput v-model="docForm.url" label="Link" placeholder="https://… (link to file)" />
      </div>
      <div class="flex justify-end mt-3">
        <button class="btn sm" :disabled="subSaving || !docForm.title" @click="addDocument">
          {{ subSaving ? 'ADDING…' : '+ ADD DOCUMENT' }}
        </button>
      </div>
      <p v-if="subError" class="error mt-2 mb-0">{{ subError }}</p>
    </div>

    <!-- Documents table -->
    <DataTable
      :items="documents"
      :columns="columns"
      row-key="id"
      storage-key="exhibitor-documents"
      empty-text="No documents yet."
    >
      <template #cell-title="{ row }">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-lg bg-brand-soft text-brand flex items-center justify-center shrink-0">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
          </div>
          <span class="font-semibold text-ink text-[.88rem] truncate">{{ row.title }}</span>
        </div>
      </template>
      <template #cell-url="{ row }">
        <a v-if="row.url" :href="row.url" target="_blank" rel="noopener" class="text-brand text-[.84rem] hover:underline">{{ row.url }}</a>
        <span v-else class="muted">—</span>
      </template>
      <template #actions="{ row }">
        <button
          class="w-8 h-8 inline-flex items-center justify-center bg-transparent border-0 rounded-lg cursor-pointer text-muted hover:text-[#dc2626] hover:bg-[#fef2f2] transition-colors"
          title="Remove document"
          @click="removeDocument(row)"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
        </button>
      </template>
    </DataTable>
  </div>
</template>
