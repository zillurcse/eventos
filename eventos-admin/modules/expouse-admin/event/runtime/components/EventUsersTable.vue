<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
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
const page         = ref(1)
const perPage      = ref(25)
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

const filtered = computed(() => {
  let list = users.value
  const q = search.value.trim().toLowerCase()
  if (q) list = list.filter(u =>
    (u.name ?? '').toLowerCase().includes(q) || (u.email ?? '').toLowerCase().includes(q))
  if (filterRole.value)   list = list.filter(u => u.role === filterRole.value)
  if (filterStatus.value) list = list.filter(u => u.status === filterStatus.value)
  return list
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))
const paginated = computed(() => {
  const start = (page.value - 1) * perPage.value
  return filtered.value.slice(start, start + perPage.value)
})
const paginationLabel = computed(() => {
  if (!filtered.value.length) return '0 - 0 of 0'
  const from = (page.value - 1) * perPage.value + 1
  const to = Math.min(page.value * perPage.value, filtered.value.length)
  return `${from} - ${to} of ${filtered.value.length}`
})

watch([search, filterRole, filterStatus, perPage], () => { page.value = 1 })

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
        <div class="relative flex-1 min-w-45 max-w-70">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted pointer-events-none">
            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
          </svg>
          <input v-model="search" placeholder="Search name or email" style="padding-left:2.2rem;">
        </div>
        <select v-model="filterRole" style="width:170px;">
          <option value="">All roles</option>
          <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
        </select>
        <select v-model="filterStatus" style="width:170px;">
          <option value="">All statuses</option>
          <option v-for="s in statuses" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
        </select>
        <button class="btn ghost text-[.82rem] tracking-wide px-4 py-2" @click="resetFilters">RESET</button>
      </div>

      <!-- Table -->
      <table>
        <thead>
          <tr>
            <th>USER</th><th>CONTACT</th><th>ROLE</th><th>STATUS</th>
            <th>CHECK-IN</th><th>REGISTERED</th><th>ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="text-center py-12 muted">Loading users…</td>
          </tr>
          <tr v-for="u in paginated" v-else :key="u.id" :class="u.blocked ? 'opacity-60' : ''">
            <td>
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 bg-brand-soft text-brand flex items-center justify-center text-[.74rem] font-bold">
                  <img v-if="u.avatar_url" :src="u.avatar_url" class="w-full h-full object-cover" :alt="u.name ?? ''">
                  <span v-else>{{ initials(u.name) }}</span>
                </div>
                <div class="min-w-0">
                  <div class="font-medium text-ink truncate flex items-center gap-1.5">
                    {{ u.name || '—' }}
                    <span v-if="u.blocked" class="px-1.5 py-0.5 rounded bg-red-50 text-[#dc2626] text-[.66rem] font-bold uppercase">Blocked</span>
                    <span v-if="u.has_login" title="Has a web-app login" class="inline-flex items-center text-[#6352e7]">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                  </div>
                  <div v-if="u.company || u.job_title" class="text-[.78rem] text-muted truncate">
                    {{ [u.job_title, u.company].filter(Boolean).join(' · ') }}
                  </div>
                </div>
              </div>
            </td>
            <td>
              <div class="text-ink text-[.86rem] truncate">{{ u.email || '—' }}</div>
              <div v-if="u.phone" class="text-[.78rem] text-muted">{{ u.phone }}</div>
            </td>
            <td><span class="capitalize text-ink">{{ u.role }}</span></td>
            <td>
              <span class="px-2 py-0.5 rounded-full text-[.72rem] font-semibold capitalize" :class="STATUS_STYLE[u.status] ?? 'bg-gray-100 text-gray-500'">
                {{ u.status.replace('_', ' ') }}
              </span>
            </td>
            <td>
              <span v-if="u.checked_in" class="inline-flex items-center gap-1 text-green-600 text-[.82rem] font-medium">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3.5 h-3.5"><path d="M20 6L9 17l-5-5"/></svg>
                {{ fmtDate(u.checked_in_at) }}
              </span>
              <span v-else class="text-muted text-[.82rem]">Not yet</span>
            </td>
            <td class="text-muted text-[.84rem]">{{ fmtDate(u.registered_at) }}</td>
            <td>
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
            </td>
          </tr>

          <tr v-if="!loading && !paginated.length">
            <td colspan="7" class="text-center py-12 muted">
              {{ users.length ? 'No users match your filters.' : emptyText }}
            </td>
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
  </div>
</template>
