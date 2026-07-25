<script setup lang="ts">
import { computed } from 'vue'
import { TYPE_OPTIONS } from '../../utils/exhibitor'

const {
  eventId, exhibitors, packages, openAdd,
  search, filterType, filterPackage, resetFilters, filtered,
  packageName, toggleActions, actionsOpenId, remove,
  openResetPassword, toggleStatus, previous,
} = useExhibitorContext()

const typeOptions = TYPE_OPTIONS.map((t: string) => ({ value: t.toLowerCase(), label: t }))
const packageOptions = computed(() => packages.value.map((pkg: any) => ({ value: String(pkg.id), label: pkg.name })))
const filtersActive = computed(() => !!(search.value.trim() || filterType.value || filterPackage.value))

// TODO: wire to a real org-wide exhibitor directory once it exists.
function openDirectory() {
  alert('Coming soon.')
}

const columns = [
  { key: 'image', label: 'Image', width: '68px' },
  { key: 'name', label: 'Name' },
  { key: 'type', label: 'Type' },
  { key: 'status', label: 'Status' },
  { key: 'package', label: 'Package' },
  { key: 'team', label: 'Team' },
  { key: 'stall_no', label: 'Stall no' },
]
</script>

<template>
  <div>
    <!-- Header row -->
    <div class="flex items-start justify-between gap-4 mb-5">
      <div>
        <div class="flex flex-col gap-1.5">
          <div class="inline-flex">
            <span class="bg-brand text-white text-[.76rem] font-bold px-3 py-1 rounded-full leading-none">
              {{ exhibitors.length }} of {{ EXHIBITOR_LIMIT }}
            </span>
          </div>
          <div class="w-52 h-1.75 bg-line rounded-full overflow-hidden">
            <div
              class="h-full bg-brand rounded-full transition-all"
              :style="{ width: Math.min(100, Math.round((exhibitors.length / EXHIBITOR_LIMIT) * 100)) + '%' }"
            />
          </div>
        </div>
      </div>
      <button class="btn ghost text-[.82rem] tracking-wide px-4 py-2 shrink-0 h-10 capitalize" @click="previous.openPicker">
        Previous Exhibitors
      </button>
    </div>

    <!-- Toolbar: search + filters + directory/add -->
    <div class="flex items-center gap-3 mb-5 flex-wrap">
      <SearchInput v-model="search" placeholder="Search" class="flex-1 min-w-45 max-w-70" />
      

      <div class="flex items-center gap-2 ml-auto shrink-0">
        <AppSelect
          v-model="filterType"
          placeholder="All Type"
          class="w-[170px]"
          :options="typeOptions"
        />
        <AppSelect
          v-model="filterPackage"
          placeholder="All Packages"
          class="w-[190px]"
          :options="packageOptions"
        />
        <button
          v-if="filtersActive"
          class="inline-flex items-center gap-1.5 text-[.85rem] font-semibold text-brand bg-transparent border-0 cursor-pointer hover:text-brand-dark"
          @click="resetFilters"
        >
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
          Clear filters
        </button>
        <button
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-md bg-[#F0EEFD] text-brand font-[650] text-[.82rem] border-0 cursor-pointer hover:bg-[#e9e7f8] h-10"
          @click="openDirectory"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
          Exhibitor Directory
        </button>
        <button class="btn text-[.82rem] tracking-wide px-4 py-2 h-10" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          Add Exhibitor
        </button>
      </div>
    </div>

    <!-- Table -->
    <DataTable
      :items="filtered"
      :columns="columns"
      row-key="id"
      storage-key="exhibitors"
      empty-text="No exhibitors found."
    >
      <template #cell-image="{ row }">
        <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0">
          <img v-if="row.logo_url" :src="row.logo_url" class="w-full h-full object-cover" :alt="row.name">
          <div v-else class="w-full h-full bg-brand-soft flex items-center justify-center text-brand font-bold text-[.78rem] uppercase">
            {{ exhibitorInitials(row.name) }}
          </div>
        </div>
      </template>
      <template #cell-name="{ row }">
        <span class="font-medium text-ink">{{ row.name }}</span>
      </template>
      <template #cell-type="{ row }">
        <span class="capitalize text-ink">{{ row.type || 'Exhibitor' }}</span>
      </template>
      <template #cell-status="{ row }">
        <span class="font-medium text-[.9rem]" :class="isActive(row) ? 'text-green-600' : 'text-muted'">
          {{ exhibitorStatusLabel(row) }}
        </span>
      </template>
      <template #cell-package="{ row }">
        <span class="font-semibold text-ink">{{ packageName(row.package_id) }}</span>
      </template>
      <template #cell-team="{ row }">
        <span class="text-muted">{{ row.members_count ?? 0 }} of {{ row.team_limit ?? 1 }}</span>
      </template>
      <template #cell-stall_no="{ row }">
        <span class="text-muted">{{ row.stall_no || '' }}</span>
      </template>
      <template #actions="{ row }">
        <div class="relative inline-block" @click.stop>
          <button class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:bg-[#f1f2f6] border-0 bg-transparent cursor-pointer" aria-label="Actions" @click="toggleActions(row.id)">
            <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
          </button>
          <div v-if="actionsOpenId === row.id" class="absolute right-0 top-full mt-1 bg-white border border-line rounded-xl shadow-lg z-20 min-w-48 overflow-hidden divide-y divide-line">
            <NuxtLink :to="`/org/events/${eventId}/showcase/exhibitors/${row.id}`" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.88rem] no-underline hover:bg-[#f7f8fa] text-ink transition-colors" @click="actionsOpenId = null">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              Edit
            </NuxtLink>
            <button class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-ink transition-colors" @click="toggleStatus(row)">
              <template v-if="isActive(row)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-[#dc2626]"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
                Deactivate
              </template>
              <template v-else>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-green-600"><path d="M20 6L9 17l-5-5"/></svg>
                Activate
              </template>
            </button>
            <button class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-ink transition-colors" @click="openResetPassword(row)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
              Reset Password
            </button>
            <button class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.88rem] hover:bg-[#fef2f2] text-[#dc2626] transition-colors" @click="remove(row); actionsOpenId = null">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              Delete
            </button>
          </div>
        </div>
      </template>
    </DataTable>
  </div>
</template>

