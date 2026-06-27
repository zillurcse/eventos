<script setup lang="ts">
const props = defineProps<{ eventId: string }>()

const mgr = useExhibitorManager(props.eventId)
provide(ExhibitorKey, mgr)
onMounted(mgr.init)

const { drawerMode, actionsOpenId } = mgr
</script>

<template>
  <div @click="actionsOpenId = null">
    <!-- Page header -->
    <div class="mb-4">
      <h2 class="section-title m-0">Exhibitors</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Manage the exhibitors that appear in your event.</p>
    </div>

    <ExhibitorTable />

    <ExhibitorAddDrawer v-if="drawerMode === 'add'" />
    <ExhibitorEditDrawer v-if="drawerMode === 'edit'" />
  </div>
</template>
