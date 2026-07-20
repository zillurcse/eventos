<script setup lang="ts">
// Full-page exhibitor editor shell: breadcrumb + top-bar actions (Deactivate /
// Reset Password), a left vertical tab rail, and the active tab's panel. Reads
// the exhibitor context provided by the [exhibitorId] route page.
const {
  eventId, current, activeTab, error,
  toggleStatus, openResetPassword, resetTarget,
} = useExhibitorContext()

const listPath = computed(() => `/org/events/${eventId}/showcase/exhibitors`)
const active = computed(() => current.value ? isActive(current.value) : false)

// Rail order mirrors EXHIBITOR_TABS; only "Details" gets a friendlier label.
const tabLabel = (t: string) => (t === 'Details' ? 'Basic Details' : t)

async function onToggleActive() {
  if (!current.value) return
  const wasActive = isActive(current.value)
  await toggleStatus(current.value)
  // toggleStatus flips server-side; keep the local record in step for the toggle.
  current.value = { ...current.value, status: wasActive ? 'suspended' : 'active' }
}
</script>

<template>
  <div>
    <!-- Breadcrumb + top-bar actions -->
    <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
      <div class="flex items-center gap-1.5 text-[.9rem] min-w-0">
        <NuxtLink :to="listPath" class="text-brand font-semibold no-underline hover:underline">Exhibitors</NuxtLink>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5 text-muted shrink-0"><path d="M9 18l6-6-6-6"/></svg>
        <span class="text-ink font-semibold truncate">{{ current?.name || '…' }}</span>
      </div>
      <div class="flex items-center gap-4 shrink-0">
        <label class="flex items-center gap-2 m-0 cursor-pointer select-none" title="Toggle whether this exhibitor is active">
          <span class="text-[.86rem] font-medium text-muted">Deactivate</span>
          <button
            type="button"
            class="relative w-10 h-5.5 rounded-full transition-colors shrink-0"
            :class="active ? 'bg-brand' : 'bg-[#d7dae1]'"
            :disabled="!current"
            @click="onToggleActive"
          >
            <span class="absolute top-0.75 left-0.75 w-4 h-4 rounded-full bg-white transition-transform" :class="active ? 'translate-x-4.5' : ''" />
          </button>
        </label>
        <button class="btn ghost text-[.82rem] px-4 py-2" :disabled="!current" @click="current && openResetPassword(current)">
          Reset Password
        </button>
      </div>
    </div>

    <!-- Rail + content -->
    <div class="flex items-start gap-5">
      <!-- Vertical tab rail -->
      <aside class="w-52.5 shrink-0 bg-white border border-line rounded-2xl p-2.5 sticky top-19.5">
        <button
          v-for="tab in EXHIBITOR_TABS" :key="tab"
          class="w-full text-left px-3.5 py-2.5 rounded-lg text-[.88rem] font-medium mb-0.5 transition-colors"
          :class="activeTab === tab ? 'bg-brand-soft text-brand font-semibold' : 'text-ink hover:bg-[#f7f8fa]'"
          @click="activeTab = tab"
        >{{ tabLabel(tab) }}</button>
      </aside>

      <!-- Active panel -->
      <div class="flex-1 min-w-0 bg-white border border-line rounded-2xl p-6">
        <!-- Still loading the record (deep link / refresh). -->
        <div v-if="!current && !error" class="py-16 text-center muted text-[.9rem]">Loading exhibitor…</div>
        <p v-else-if="error && !current" class="error py-8 text-center">{{ error }}</p>

        <template v-else>
          <ExhibitorTabsBasicDetails v-if="activeTab === 'Details'" />
          <ExhibitorTabsMembers v-else-if="activeTab === 'Members'" />
          <ExhibitorTabsDocuments v-else-if="activeTab === 'Documents'" />
          <ExhibitorTabsProjects v-else-if="activeTab === 'Projects'" />
          <ExhibitorTabsProducts v-else-if="activeTab === 'Products'" />
          <ExhibitorTabsPermissions v-else-if="activeTab === 'Permissions'" />
        </template>
      </div>
    </div>

    <ExhibitorResetPasswordModal v-if="resetTarget" />
  </div>
</template>
