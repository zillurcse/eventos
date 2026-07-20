<script setup lang="ts">
const { activeTab, drawerMode } = useExhibitorContext()
</script>

<template>
  <Drawer title="Add Exhibitor" @close="drawerMode = null">
    <!-- Sticky tabs -->
    <div class="sticky -top-7 bg-white z-10 -mx-5.5 px-5.5 border-b border-line mb-4" style="margin-top:-22px;padding-top:4px;">
      <div class="tabs-scroll flex gap-0 overflow-x-auto">
        <button
          v-for="tab in EXHIBITOR_TABS" :key="tab"
          class="px-3.5 py-3 text-[.88rem] font-[550] whitespace-nowrap border-b-2 transition-colors"
          :class="activeTab === tab ? 'border-brand text-brand font-bold' : 'border-transparent text-muted hover:text-ink'"
          @click="activeTab = tab"
        >{{ tab }}</button>
      </div>
    </div>

    <!-- The tabs are shared with the edit drawer; sub-resource adds are buffered
         locally until "Add Exhibitor" on the Details tab creates the record. -->
    <ExhibitorTabsDetails v-if="activeTab === 'Details'" />
    <ExhibitorTabsMembers v-else-if="activeTab === 'Members'" />
    <ExhibitorTabsDocuments v-else-if="activeTab === 'Documents'" />
    <ExhibitorTabsProjects v-else-if="activeTab === 'Projects'" />
    <ExhibitorTabsProducts v-else-if="activeTab === 'Products'" />
    <ExhibitorTabsPermissions v-else-if="activeTab === 'Permissions'" />
  </Drawer>
</template>

<style scoped>
.tabs-scroll {
  scrollbar-width: thin;
  scrollbar-color: #d7dae1 transparent;
}
.tabs-scroll::-webkit-scrollbar {
  height: 3px;
}
.tabs-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.tabs-scroll::-webkit-scrollbar-thumb {
  background: #d7dae1;
  border-radius: 3px;
}
.tabs-scroll::-webkit-scrollbar-thumb:hover {
  background: #b9bec9;
}
</style>
