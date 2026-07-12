<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

/**
 * Shared event "people" table — powers the Users › All / Blocked / WebApp tabs.
 * `query` is merged into the GET /events/{id}/participants request (e.g.
 * { blocked: 1 } or { has_login: 1 }); search / role / status filters and
 * pagination run client-side over the returned scope.
 */
const props = withDefaults(defineProps<{
  title: string
  subtitle: string
  query?: Record<string, string | number>
  emptyText?: string
  countLabel?: string
}>(), { query: () => ({}), emptyText: 'No users yet.', countLabel: 'user' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Participant {
  id: string
  name: string | null
  email: string | null
  phone: string | null
  company: string | null
  job_title: string | null
  role: string
  status: string
  blocked: boolean
  has_login: boolean
  checked_in: boolean
  checked_in_at: string | null
  registered_at: string | null
  avatar_url: string | null
}

const users   = ref<Participant[]>([])
const loading = ref(true)

const search       = ref('')
const filterRole   = ref('')
const filterStatus = ref('')
const actionsOpenId = ref<string | null>(null)

const STATUS_STYLE: Record<string, string> = {
  registered: 'bg-blue-50 text-blue-700',
  confirmed:  'bg-green-50 text-green-700',
  checked_in: 'bg-emerald-50 text-emerald-700',
  canceled:   'bg-gray-100 text-gray-500',
  no_show:    'bg-amber-50 text-amber-700',
}

const roles = computed(() => [...new Set(users.value.map(u => u.role))].sort())
const statuses = computed(() => [...new Set(users.value.map(u => u.status))].sort())
const roleOptions = computed(() => [{ label: 'All roles', value: '' }, ...roles.value.map(r => ({ label: r, value: r }))])
const statusOptions = computed(() => [{ label: 'All statuses', value: '' }, ...statuses.value.map(s => ({ label: s.replace('_', ' '), value: s }))])

const columns = [
  { key: 'name', label: 'User', sortable: true },
  { key: 'email', label: 'Contact' },
  { key: 'role', label: 'Role', sortable: true },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'checked_in', label: 'Check-in' },
  { key: 'registered_at', label: 'Registered', sortable: true },
]
function userSearchText(u: Participant) { return `${u.name ?? ''} ${u.email ?? ''}` }
function userFilter(u: Participant) {
  return (!filterRole.value || u.role === filterRole.value)
    && (!filterStatus.value || u.status === filterStatus.value)
}

function initials(name: string | null): string {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}
function fmtDate(iso: string | null): string {
  if (!iso) return '—'
  try { return new Date(iso).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) }
  catch { return '—' }
}

function queryString(): string {
  const params = new URLSearchParams(
    Object.entries(props.query).map(([k, v]) => [k, String(v)]),
  )
  const s = params.toString()
  return s ? `?${s}` : ''
}

async function load() {
  loading.value = true
  try {
    users.value = (await api<any>(`/events/${id}/participants${queryString()}`)).data
  } catch { users.value = [] }
  finally { loading.value = false }
}

function toggleActions(uid: string) {
  actionsOpenId.value = actionsOpenId.value === uid ? null : uid
}

async function setBlocked(u: Participant, blocked: boolean) {
  actionsOpenId.value = null
  try {
    await api(`/events/${id}/participants/${u.id}/block`, { method: 'POST', body: { blocked } })
    await load()   // reload so the row leaves/stays per the current scope
    toast.success(blocked ? 'User blocked' : 'User unblocked')
  } catch (e: any) { toast.error(e?.data?.message || 'Could not update user.') }
}

async function remove(u: Participant) {
  actionsOpenId.value = null
  if (!confirm(`Remove ${u.name || u.email || 'this user'} from the event?`)) return
  try {
    await api(`/events/${id}/participants/${u.id}`, { method: 'DELETE' })
    users.value = users.value.filter(x => x.id !== u.id)
    toast.success('User removed')
  } catch (e: any) { toast.error(e?.data?.message || 'Could not remove user.') }
}

function resetFilters() { search.value = ''; filterRole.value = ''; filterStatus.value = '' }

onMounted(load)
</script>

<template>
  <div @click="actionsOpenId = null">
    <div class="card">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
        <div>
          <div class="font-bold text-base">{{ title }}</div>
          <div class="muted text-[.83rem] mt-0.5">{{ subtitle }}</div>
          <div class="mt-2.5 inline-flex">
            <span class="bg-brand text-white text-[.76rem] font-bold px-3 py-1 rounded-full leading-none">
              {{ users.length }} {{ countLabel }}{{ users.length !== 1 ? 's' : '' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="flex items-center gap-3 mb-5 flex-wrap">
        <SearchInput v-model="search" placeholder="Search name or email" class="flex-1 min-w-45 max-w-70" />
        <FilterSelect v-model="filterRole" label="Role" :options="roleOptions" />
        <FilterSelect v-model="filterStatus" label="Status" :options="statusOptions" />
        <button class="btn ghost text-[.82rem] tracking-wide px-4 py-2" @click="resetFilters">RESET</button>
      </div>

      <div v-if="loading" class="flex items-center justify-center gap-2.5 py-14 text-muted text-[.88rem]">
        <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        Loading users…
      </div>

      <!-- Table -->
      <DataTable
        v-else
        :items="users"
        :columns="columns"
        :search="search"
        :search-text="userSearchText"
        :filter="userFilter"
        row-key="id"
        storage-key="event-users"
      >
        <template #cell-name="{ row: u }">
          <div class="flex items-center gap-2.5" :class="u.blocked ? 'opacity-60' : ''">
            <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 bg-brand-soft text-brand flex items-center justify-center text-[.74rem] font-bold">
              <img v-if="u.avatar_url" :src="u.avatar_url" class="w-full h-full object-cover" :alt="u.name ?? ''">
              <span v-else>{{ initials(u.name) }}</span>
            </div>
            <div class="min-w-0">
              <div class="font-medium text-ink truncate flex items-center gap-1.5">
                {{ u.name || '—' }}
                <span v-if="u.blocked" class="px-1.5 py-0.5 rounded bg-red-50 text-[#dc2626] text-[.66rem] font-bold uppercase">Blocked</span>
                <span v-if="u.has_login" title="Has a web-app login" class="inline-flex items-center text-brand">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
              </div>
              <div v-if="u.company || u.job_title" class="text-[.78rem] text-muted truncate">
                {{ [u.job_title, u.company].filter(Boolean).join(' · ') }}
              </div>
            </div>
          </div>
        </template>
        <template #cell-email="{ row: u }">
          <div class="text-ink text-[.86rem] truncate">{{ u.email || '—' }}</div>
          <div v-if="u.phone" class="text-[.78rem] text-muted">{{ u.phone }}</div>
        </template>
        <template #cell-role="{ row: u }"><span class="capitalize text-ink">{{ u.role }}</span></template>
        <template #cell-status="{ row: u }">
          <span class="px-2 py-0.5 rounded-full text-[.72rem] font-semibold capitalize" :class="STATUS_STYLE[u.status] ?? 'bg-gray-100 text-gray-500'">
            {{ u.status.replace('_', ' ') }}
          </span>
        </template>
        <template #cell-checked_in="{ row: u }">
          <span v-if="u.checked_in" class="inline-flex items-center gap-1 text-green-600 text-[.82rem] font-medium">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3.5 h-3.5"><path d="M20 6L9 17l-5-5"/></svg>
            {{ fmtDate(u.checked_in_at) }}
          </span>
          <span v-else class="text-muted text-[.82rem]">Not yet</span>
        </template>
        <template #cell-registered_at="{ row: u }"><span class="text-muted text-[.84rem]">{{ fmtDate(u.registered_at) }}</span></template>
        <template #actions="{ row: u }">
          <div class="relative inline-block" @click.stop>
            <button class="btn flex items-center gap-1.5 text-[.82rem] tracking-wide px-4 py-2" @click="toggleActions(u.id)">
              ACTIONS
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3.5 h-3.5 transition-transform" :class="actionsOpenId === u.id ? 'rotate-180' : ''"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div v-if="actionsOpenId === u.id" class="absolute right-0 top-full mt-1 bg-white border border-line rounded-xl shadow-lg z-20 min-w-40 overflow-hidden">
              <button v-if="!u.blocked" class="w-full text-left px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-ink transition-colors" @click="setBlocked(u, true)">Block user</button>
              <button v-else class="w-full text-left px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-ink transition-colors" @click="setBlocked(u, false)">Unblock user</button>
              <button class="w-full text-left px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-[#dc2626] transition-colors" @click="remove(u)">Remove</button>
            </div>
          </div>
        </template>
        <template #empty>
          <span class="muted">{{ users.length ? 'No users match your filters.' : emptyText }}</span>
        </template>
      </DataTable>
    </div>
  </div>
</template>
