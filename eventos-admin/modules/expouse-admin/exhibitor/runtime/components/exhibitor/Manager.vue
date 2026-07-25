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
    <div class="mb-5">
      <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Exhibitors</h1>
      <p class="text-muted text-[.88rem]">Manage the exhibitors that appear in your event.</p>
    </div>

    <ExhibitorTable />

    <ExhibitorAddDrawer v-if="drawerMode === 'add'" />
    <ExhibitorPreviousDrawer v-if="previousOpen" />
    <ExhibitorResetPasswordModal v-if="resetTarget" />
  </div>
</template>
