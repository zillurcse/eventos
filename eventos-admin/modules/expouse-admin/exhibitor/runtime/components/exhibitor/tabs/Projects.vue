<script setup lang="ts">
const { projectForm, subSaving, subError, projects, addProject, removeProject } = useExhibitorContext()
</script>

<template>
  <div>
    <div class="border border-line rounded-xl p-4 mb-4">
      <p class="font-semibold text-[.92rem] m-0 mb-2">Add a project</p>
      <div class="flex flex-wrap gap-2 items-end">
        <AppInput v-model="projectForm.name" placeholder="Project name" class="flex-[1_1_180px]" />
        <AppInput v-model="projectForm.description" placeholder="Description" class="flex-[1_1_220px]" />
        <AppInput v-model="projectForm.status" placeholder="Status" class="flex-[0_1_130px]" />
        <button class="btn sm" :disabled="subSaving || !projectForm.name" @click="addProject">ADD</button>
      </div>
    </div>
    <table>
      <thead><tr><th>Name</th><th>Description</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
        <tr v-for="pr in projects" :key="pr.id">
          <td class="font-semibold text-ink">{{ pr.name }}</td>
          <td class="muted text-[.84rem]">{{ pr.description || '—' }}</td>
          <td><span v-if="pr.status" class="badge">{{ pr.status }}</span><span v-else class="muted">—</span></td>
          <td class="text-right"><button class="bg-transparent border-0 cursor-pointer text-[#dc2626]" title="Remove" @click="removeProject(pr)">🗑</button></td>
        </tr>
        <tr v-if="!projects.length"><td colspan="4" class="muted text-center py-8">No projects yet.</td></tr>
      </tbody>
    </table>
    <p v-if="subError" class="error mt-2">{{ subError }}</p>
  </div>
</template>
