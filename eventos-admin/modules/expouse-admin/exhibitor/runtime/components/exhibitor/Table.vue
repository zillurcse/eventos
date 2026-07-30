<script setup lang="ts">
import { computed } from 'vue'
import { toast } from 'vue-sonner'
import { TYPE_OPTIONS } from '../../utils/exhibitor'

const {
  eventId, exhibitors, packages, openAdd,
  search, filterType, filterPackage, resetFilters, filtered,
  packageName, toggleActions, actionsOpenId, actionsAnchor, closeActions, remove,
  openResetPassword, toggleStatus, previous,
} = useExhibitorContext()

const typeOptions = TYPE_OPTIONS.map((t: string) => ({ value: t.toLowerCase(), label: t }))
const packageOptions = computed(() => packages.value.map((pkg: any) => ({ value: String(pkg.id), label: pkg.name })))
const filtersActive = computed(() => !!(search.value.trim() || filterType.value || filterPackage.value))

// TODO: wire to a real org-wide exhibitor directory once it exists.
function openDirectory() {
  toast.info('Coming soon.')
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
        <div class="min-h-[47px] flex items-center">
          <div class="w-14 h-10 rounded-lg overflow-hidden shrink-0">
              <img v-if="row.logo_url" :src="row.logo_url" class="w-full h-full object-cover" :alt="row.name">
              <div v-else class="w-full h-full bg-[#F0EEFD] flex items-center justify-center text-brand font-bold text-[.78rem] uppercase">
                {{ exhibitorInitials(row.name) }}
              </div>
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
          <button class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:bg-[#f1f2f6] border-0 bg-transparent cursor-pointer" aria-label="Actions" @click="toggleActions(row.id, $event)">
            <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
          </button>
          <Teleport
            v-if="actionsOpenId === row.id && actionsAnchor"
            to="body"
          >
            <div
              class="fixed bg-white rounded-xl border border-[#E8E8EE] shadow-xl z-30 min-w-40 overflow-hidden p-2"
              :style="{ top: `${actionsAnchor.top}px`, right: `${actionsAnchor.right}px` }"
              @click.stop
            >
              <NuxtLink
                :to="`/org/events/${eventId}/showcase/exhibitors/${row.id}`"
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg max-h-10 text-[.92rem] font-medium text-brand hover:bg-[#F7F7FB] no-underline transition-colors"
                @click="closeActions"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15" fill="none">
                    <path d="M2.25 12.0043H5.43C5.5287 12.0049 5.62655 11.986 5.71793 11.9487C5.80931 11.9114 5.89242 11.8564 5.9625 11.7868L11.1525 6.58935L13.2825 4.50435C13.3528 4.43463 13.4086 4.35168 13.4467 4.26028C13.4847 4.16889 13.5043 4.07086 13.5043 3.97185C13.5043 3.87284 13.4847 3.77481 13.4467 3.68342C13.4086 3.59202 13.3528 3.50907 13.2825 3.43935L10.1025 0.221849C10.0328 0.151552 9.94983 0.0957567 9.85843 0.0576802C9.76704 0.0196037 9.66901 0 9.57 0C9.47099 0 9.37296 0.0196037 9.28157 0.0576802C9.19017 0.0957567 9.10722 0.151552 9.0375 0.221849L6.9225 2.34435L1.7175 7.54185C1.64799 7.61193 1.593 7.69504 1.55567 7.78642C1.51835 7.8778 1.49943 7.97564 1.5 8.07435V11.2543C1.5 11.4533 1.57902 11.644 1.71967 11.7847C1.86032 11.9253 2.05109 12.0043 2.25 12.0043ZM9.57 1.81185L11.6925 3.93435L10.6275 4.99935L8.505 2.87685L9.57 1.81185ZM3 8.38185L7.4475 3.93435L9.57 6.05685L5.1225 10.5043H3V8.38185ZM14.25 13.5043H0.75C0.551088 13.5043 0.360322 13.5834 0.21967 13.724C0.0790176 13.8647 0 14.0554 0 14.2543C0 14.4533 0.0790176 14.644 0.21967 14.7847C0.360322 14.9253 0.551088 15.0043 0.75 15.0043H14.25C14.4489 15.0043 14.6397 14.9253 14.7803 14.7847C14.921 14.644 15 14.4533 15 14.2543C15 14.0554 14.921 13.8647 14.7803 13.724C14.6397 13.5834 14.4489 13.5043 14.25 13.5043Z" fill="#6452E7"/>
                  </svg>
                Edit
              </NuxtLink>
              <button
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg max-h-10 text-[.92rem] font-medium text-ink hover:bg-[#f7f8fa] cursor-pointer bg-transparent border-0 text-left transition-colors"
                @click="toggleStatus(row)"
              >
                <template v-if="isActive(row)">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-[#dc2626]"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>
                  Deactivate
                </template>
                <template v-else>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-green-600"><path d="M20 6L9 17l-5-5"/></svg>
                  Activate
                </template>
              </button>
              <button
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg max-h-10 text-[.92rem] font-medium text-ink hover:bg-[#f7f8fa] cursor-pointer bg-transparent border-0 text-left transition-colors"
                @click="openResetPassword(row)"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                Reset Password
              </button>
              <button
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg max-h-10 text-[.92rem] font-medium text-ink hover:bg-[#f7f8fa] cursor-pointer bg-transparent border-0 text-left transition-colors"
                @click="closeActions(); remove(row)"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6"/><path d="M10 11v6M14 11v6"/></svg>
                Delete
              </button>
            </div>
          </Teleport>
        </div>
      </template>
    </DataTable>
  </div>
</template>

