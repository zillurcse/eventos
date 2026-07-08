<script setup lang="ts">
const { docForm, subSaving, subError, documents, addDocument, removeDocument } = useExhibitorContext()
</script>

<template>
  <div>
    <div class="border border-line rounded-xl p-4 mb-4">
      <p class="font-semibold text-[.92rem] m-0 mb-2">Add a document</p>
      <div class="flex flex-wrap gap-2 items-end">
        <AppInput v-model="docForm.title" placeholder="Title" class="flex-[1_1_180px]" />
        <AppInput v-model="docForm.url" placeholder="https://… (link to file)" class="flex-[1_1_220px]" />
        <button class="btn sm" :disabled="subSaving || !docForm.title" @click="addDocument">ADD</button>
      </div>
    </div>
    <table>
      <thead><tr><th>Title</th><th>Link</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
        <tr v-for="d in documents" :key="d.id">
          <td class="font-semibold text-ink">{{ d.title }}</td>
          <td><a v-if="d.url" :href="d.url" target="_blank" class="text-brand text-[.84rem]">{{ d.url }}</a><span v-else class="muted">—</span></td>
          <td class="text-right"><button class="bg-transparent border-0 cursor-pointer text-[#dc2626]" title="Remove" @click="removeDocument(d)">🗑</button></td>
        </tr>
        <tr v-if="!documents.length"><td colspan="3" class="muted text-center py-8">No documents yet.</td></tr>
      </tbody>
    </table>
    <p v-if="subError" class="error mt-2">{{ subError }}</p>
  </div>
</template>
