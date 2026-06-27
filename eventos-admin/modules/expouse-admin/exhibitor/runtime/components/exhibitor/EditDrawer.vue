<script setup lang="ts">
const { activeTab, editingId, drawerMode } = useExhibitorContext()
</script>

<template>
  <Drawer :key="'edit-' + editingId" title="Edit Exhibitor" @close="drawerMode = null">
    <!-- Sticky tabs -->
    <div class="sticky top-0 bg-white z-10 -mx-5.5 px-5.5 border-b border-line mb-4" style="margin-top:-22px;padding-top:4px;">
      <div class="flex gap-0 overflow-x-auto">
        <button
          v-for="tab in EXHIBITOR_TABS" :key="tab"
          class="px-3.5 py-3 text-[.88rem] font-[550] whitespace-nowrap border-b-2 transition-colors"
          :class="activeTab === tab ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink'"
          @click="activeTab = tab"
        >{{ tab }}</button>
      </div>
    </div>

    <ExhibitorTabsDetails v-if="activeTab === 'Details'" />
    <ExhibitorTabsMembers v-else-if="activeTab === 'Members'" />
    <ExhibitorTabsDocuments v-else-if="activeTab === 'Documents'" />
    <ExhibitorTabsProjects v-else-if="activeTab === 'Projects'" />
    <ExhibitorTabsProducts v-else-if="activeTab === 'Products'" />
    <ExhibitorTabsPermissions v-else-if="activeTab === 'Permissions'" />
  </Drawer>
</template>
