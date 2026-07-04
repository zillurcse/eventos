<script setup lang="ts">
const props = defineProps<{ event: any, to: string, allowPublish?: boolean }>()
const emit = defineEmits<{ (e: 'edit'): void, (e: 'publish'): void, (e: 'delete'): void }>()
</script>

<template>
  <NuxtLink :to="props.to">Manage</NuxtLink>
  <button @click="emit('edit')">Edit</button>
  <button v-if="allowPublish && event.status === 'draft'" @click="emit('publish')">Publish</button>
  <button class="danger" @click="emit('delete')">Delete</button>
</template>

<style scoped>
.danger {
  color: #dc2626;
  border-top: 1px solid var(--line);
  margin-top: 4px;
  padding-top: 9px !important;
}
.danger:hover {
  background: #fef2f2 !important;
}
</style>
