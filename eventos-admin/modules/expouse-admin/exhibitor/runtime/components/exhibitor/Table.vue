<script setup lang="ts">
const {
  exhibitors, packages, openAdd,
  search, filterType, filterPackage, resetFilters,
  paginated, filtered, perPage, page, totalPages, paginationLabel,
  packageName, toggleActions, actionsOpenId, openEdit, remove,
  openResetPassword, toggleStatus,
} = useExhibitorContext()
</script>

<template>
  <div class="card">
    <!-- Card header row -->
    <div class="flex items-start justify-between gap-4 mb-5">
      <div>
        <div class="font-bold text-base">Exhibitors</div>
        <div class="muted text-[.83rem] mt-0.5">Events exhibitors. Use drag and drop to rearrange the position</div>
        <div class="mt-2.5 flex flex-col gap-1.5">
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
      <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
        <button class="btn ghost text-[.82rem] tracking-wide px-4 py-2">PREVIOUS EXHIBITORS</button>
        <button class="btn ghost text-[.82rem] px-4 py-2">Exhibitors Directory</button>
        <button class="btn text-[.82rem] tracking-wide px-4 py-2" @click="openAdd">
          + EXHIBITOR
        </button>
      </div>
    </div>

    <!-- Filters row -->
    <div class="flex items-center gap-3 mb-5 flex-wrap">
      <div class="relative flex-1 min-w-45 max-w-70">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted pointer-events-none">
          <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
        </svg>
        <input v-model="search" placeholder="Search Exhibitors" style="padding-left:2.2rem;">
      </div>
      <select v-model="filterType" style="width:170px;">
        <option value="">Select Type</option>
        <option v-for="t in TYPE_OPTIONS" :key="t" :value="t.toLowerCase()">{{ t }}</option>
      </select>
      <select v-model="filterPackage" style="width:190px;">
        <option value="">Select Package</option>
        <option v-for="pkg in packages" :key="pkg.id" :value="String(pkg.id)">{{ pkg.name }}</option>
      </select>
      <button class="btn ghost text-[.82rem] tracking-wide px-4 py-2" @click="resetFilters">RESET FILTERS</button>
    </div>

    <!-- Table -->
    <table>
      <thead>
        <tr>
          <th>IMAGE</th><th>NAME</th><th>TYPE</th><th>STATUS</th>
          <th>PACKAGE</th><th>TEAM</th><th>STALL NO</th><th>ACTIONS</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in paginated" :key="p.id">
          <td>
            <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0">
              <img v-if="p.logo_url" :src="p.logo_url" class="w-full h-full object-cover" :alt="p.name">
              <div v-else class="w-full h-full bg-brand-soft flex items-center justify-center text-brand font-bold text-[.78rem] uppercase">
                {{ exhibitorInitials(p.name) }}
              </div>
            </div>
          </td>
          <td class="font-medium text-ink">{{ p.name }}</td>
          <td class="capitalize text-ink">{{ p.type || 'Exhibitor' }}</td>
          <td>
            <span class="font-medium text-[.9rem]" :class="(p.status || 'active') === 'active' ? 'text-green-600' : 'text-muted'">
              {{ exhibitorStatusLabel(p) }}
            </span>
          </td>
          <td class="font-semibold text-ink">{{ packageName(p.package_id) }}</td>
          <td class="text-muted">{{ p.members_count ?? 0 }} of {{ p.team_limit ?? 1 }}</td>
          <td class="text-muted">{{ p.stall_no || '' }}</td>
          <td>
            <div class="relative inline-block" @click.stop>
              <button class="btn flex items-center gap-1.5 text-[.82rem] tracking-wide px-4 py-2" @click="toggleActions(p.id)">
                ACTIONS
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3.5 h-3.5 transition-transform" :class="actionsOpenId === p.id ? 'rotate-180' : ''">
                  <path d="M6 9l6 6 6-6"/>
                </svg>
              </button>
              <div v-if="actionsOpenId === p.id" class="absolute right-0 top-full mt-1 bg-white border border-line rounded-xl shadow-lg z-20 min-w-44 overflow-hidden">
                <button class="w-full text-left px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-ink transition-colors" @click="openEdit(p); actionsOpenId = null">Edit</button>
                <button class="w-full text-left px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-ink transition-colors" @click="toggleStatus(p)">
                  {{ (p.status || 'active') === 'active' ? 'Deactivate' : 'Activate' }}
                </button>
                <button class="w-full text-left px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-ink transition-colors" @click="openResetPassword(p)">Reset Password</button>
                <button class="w-full text-left px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-[#dc2626] transition-colors" @click="remove(p); actionsOpenId = null">Delete</button>
              </div>
            </div>
          </td>
        </tr>

        <tr v-if="!paginated.length">
          <td colspan="8" class="text-center py-12 muted">No exhibitors found.</td>
        </tr>
      </tbody>
    </table>

    <!-- Pagination -->
    <div v-if="filtered.length > 0" class="flex items-center justify-end gap-4 mt-4 pt-4 border-t border-line flex-wrap">
      <div class="flex items-center gap-2 text-[.85rem] text-muted">
        <span>Nb / page</span>
        <select v-model="perPage" style="width:64px;padding:6px 8px;font-size:.84rem;">
          <option :value="10">10</option><option :value="25">25</option><option :value="50">50</option>
        </select>
      </div>
      <div class="flex items-center gap-2 text-[.85rem] text-muted">
        <span>Page</span>
        <select v-model="page" style="width:64px;padding:6px 8px;font-size:.84rem;">
          <option v-for="n in totalPages" :key="n" :value="n">{{ n }}</option>
        </select>
      </div>
      <span class="text-[.85rem] text-muted">{{ paginationLabel }}</span>
      <div class="flex items-center gap-1">
        <button class="w-7 h-7 flex items-center justify-center border border-line rounded-lg hover:bg-[#f0f0f7] disabled:opacity-40 transition-colors" :disabled="page <= 1" @click="page--">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <button class="w-7 h-7 flex items-center justify-center border border-line rounded-lg hover:bg-[#f0f0f7] disabled:opacity-40 transition-colors" :disabled="page >= totalPages" @click="page++">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M9 18l6-6-6-6"/></svg>
        </button>
      </div>
    </div>
  </div>
</template>
