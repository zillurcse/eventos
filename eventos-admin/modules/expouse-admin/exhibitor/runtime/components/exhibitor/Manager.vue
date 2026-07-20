<script setup lang="ts">
const props = defineProps<{ eventId: string }>()

const mgr = useExhibitorManager(props.eventId)
provide(ExhibitorKey, mgr)
onMounted(mgr.init)

const { drawerMode, actionsOpenId, resetTarget } = mgr
// A top-level ref so the template unwraps it — nested refs on a plain object don't.
const previousOpen = mgr.previous.open
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
    <ExhibitorPreviousDrawer v-if="previousOpen" />
    <ExhibitorResetPasswordModal v-if="resetTarget" />
  </div>
</template>
