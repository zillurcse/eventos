<script setup lang="ts">
const { projectForm, subSaving, subError, projects, addProject, removeProject } = useExhibitorContext()

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'description', label: 'Description' },
  { key: 'status', label: 'Status' },
]
</script>

<template>
  <div>
    <!-- Add project form -->
    <div class="border border-line rounded-xl p-4 mb-5 bg-[#f7f8fa]">
      <p class="font-semibold text-[.92rem] m-0 mb-3 text-ink">Add a project</p>
      <div class="grid gap-2">
        <AppInput v-model="projectForm.name" label="Project name" placeholder="Project name" />
        <AppInput v-model="projectForm.description" label="Description" placeholder="Description" />
        <AppInput v-model="projectForm.status" label="Status" placeholder="e.g. Ongoing" />
      </div>
      <div class="flex justify-end mt-3">
        <button class="btn sm" :disabled="subSaving || !projectForm.name" @click="addProject">
          {{ subSaving ? 'ADDING…' : '+ ADD PROJECT' }}
        </button>
      </div>
      <p v-if="subError" class="error mt-2 mb-0">{{ subError }}</p>
    </div>

    <!-- Projects table -->
    <DataTable
      :items="projects"
      :columns="columns"
      row-key="id"
      storage-key="exhibitor-projects"
      empty-text="No projects yet."
    >
      <template #cell-name="{ row }">
        <span class="font-semibold text-ink text-[.88rem]">{{ row.name }}</span>
      </template>
      <template #cell-description="{ row }">
        <span class="muted text-[.84rem]">{{ row.description || '—' }}</span>
      </template>
      <template #cell-status="{ row }">
        <span v-if="row.status" class="badge">{{ row.status }}</span>
        <span v-else class="muted">—</span>
      </template>
      <template #actions="{ row }">
        <button
          class="w-8 h-8 inline-flex items-center justify-center bg-transparent border-0 rounded-lg cursor-pointer text-muted hover:text-[#dc2626] hover:bg-[#fef2f2] transition-colors"
          title="Remove project"
          @click="removeProject(row)"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
        </button>
      </template>
    </DataTable>
  </div>
</template>
