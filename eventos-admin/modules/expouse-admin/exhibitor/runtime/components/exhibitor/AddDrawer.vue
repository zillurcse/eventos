<script setup lang="ts">
const { activeTab, drawerMode, draft, canCreate, saving, create, update } = useExhibitorContext()

const isAdd = computed(() => drawerMode.value === 'add')
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
    <ExhibitorTabsDetails v-if="activeTab === 'Details'" :show-footer="false" />
    <ExhibitorTabsMembers v-else-if="activeTab === 'Teams'" />
    <ExhibitorTabsDocuments v-else-if="activeTab === 'Documents'" />
    <ExhibitorTabsProjects v-else-if="activeTab === 'Projects'" />
    <ExhibitorTabsProducts v-else-if="activeTab === 'Products'" />
    <ExhibitorTabsPermissions v-else-if="activeTab === 'Permissions'" />

    <!-- Details owns the create/update action (it's the only tab that saves the
         whole exhibitor); render it in the drawer's sticky footer instead of
         inline so it stays pinned regardless of scroll, like showcase/packages. -->
    <template v-if="activeTab === 'Details'" #footer>
      <div class="modal-actions border-t border-line px-5.5 py-4 justify-start">
        <button
          v-if="isAdd"
          class="btn"
          :disabled="saving || !canCreate"
          @click="create"
        >
          {{ saving ? 'ADDING…' : 'Add Exhibitor' }}
        </button>
        <button
          v-else
          class="btn"
          :disabled="saving || !draft.name.trim()"
          @click="update"
        >
          {{ saving ? 'UPDATING…' : 'UPDATE' }}
        </button>
        <button class="btn ghost" @click="drawerMode = null">Cancel</button>
      </div>
    </template>
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
