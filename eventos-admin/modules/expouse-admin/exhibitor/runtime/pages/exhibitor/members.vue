<script setup lang="ts">
definePageMeta({ middleware: 'exhibitor', feature: 'teams', title: 'Team Members', subtitle: 'Your booth staff and their access' })

const auth = useAuthStore()
const api = useApi()

const PERMS = [
  { key: 'scan_badges', label: 'Scan badges' },
  { key: 'view_all_leads', label: 'View all team leads' },
  { key: 'edit_lead_notes', label: 'Edit lead notes' },
  { key: 'export_leads', label: 'Export leads' },
  { key: 'manage_team', label: 'Manage team members' },
]

const members = ref<any[]>([])
const suspended = ref(false)
const search = ref('')
const page = ref(1)
const perPage = ref(10)
const actionsFor = ref<number | null>(null)

// Invite form (toggled)
const inviteOpen = ref(false)
const form = reactive({ email: '', first_name: '', last_name: '', role: 'staff', password: '' })
const adding = ref(false)
const error = ref('')

// Reset password
const pwFor = ref<number | null>(null)
const pwValue = ref('')

const isSelf = (m: any) => m.contact?.email === auth.user?.email

function initials(name?: string, email?: string) {
  const s = (name || email || '?').trim()
  const p = s.split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

async function load() {
  try {
    members.value = (await api<any>('/exhibitor/members')).data
  } catch (e: any) {
    if (e?.response?.status === 403) suspended.value = true
  }
}

// ── Stats ──
const adminCount = computed(() => members.value.filter(m => m.role === 'admin').length)
const staffCount = computed(() => members.value.filter(m => m.role !== 'admin').length)

// ── Table (search + client pagination) ──
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return members.value
  return members.value.filter(m =>
    (m.contact?.name || '').toLowerCase().includes(q) || (m.contact?.email || '').toLowerCase().includes(q))
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))
const paged = computed(() => filtered.value.slice((page.value - 1) * perPage.value, page.value * perPage.value))
const rangeLabel = computed(() => {
  if (!filtered.value.length) return '0 - 0 of 0'
  const from = (page.value - 1) * perPage.value + 1
  const to = Math.min(page.value * perPage.value, filtered.value.length)
  return `${from} - ${to} of ${filtered.value.length}`
})
watch([search, perPage], () => { page.value = 1 })

// ── Invite / remove / password ──
async function add() {
  error.value = ''
  adding.value = true
  try {
    await api('/exhibitor/members', { method: 'POST', body: { ...form } })
    Object.assign(form, { email: '', first_name: '', last_name: '', role: 'staff', password: '' })
    inviteOpen.value = false
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not add the member.'
  } finally {
    adding.value = false
  }
}
async function remove(m: any) {
  actionsFor.value = null
  if (!confirm(`Remove ${m.contact?.email}?`)) return
  await api(`/exhibitor/members/${m.id}`, { method: 'DELETE' })
  await load()
}
function openReset(m: any) { actionsFor.value = null; pwFor.value = m.id; pwValue.value = '' }
async function savePassword(m: any) {
  if (pwValue.value.length < 8) return
  await api(`/exhibitor/members/${m.id}/password`, { method: 'POST', body: { password: pwValue.value } })
  pwFor.value = null; pwValue.value = ''
  await load()
}

// ── Staff permissions panel ──
const selectedId = ref<number | null>(null)
const perms = reactive<Record<string, boolean>>({})
const permRole = ref<'admin' | 'staff'>('staff')
const savingPerms = ref(false)

const selectableMembers = computed(() => members.value)
const selected = computed(() => members.value.find(m => m.id === selectedId.value) || null)

watch(selectedId, (id) => {
  const m = members.value.find(x => x.id === id)
  permRole.value = m?.role === 'admin' ? 'admin' : 'staff'
  for (const p of PERMS) perms[p.key] = m?.role === 'admin' ? true : !!m?.permissions?.[p.key]
})

async function togglePerm(key: string) {
  if (!selected.value || permRole.value === 'admin') return
  perms[key] = !perms[key]
  await savePerms()
}
async function savePerms() {
  if (!selected.value) return
  savingPerms.value = true
  try {
    const res = await api<any>(`/exhibitor/members/${selected.value.id}`, {
      method: 'PATCH',
      body: { permissions: { ...perms } },
    })
    const i = members.value.findIndex(m => m.id === selected.value!.id)
    if (i >= 0) members.value[i] = res.data
  } finally {
    savingPerms.value = false
  }
}

onMounted(load)
</script>

<template>
  <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

  <div v-else @click="actionsFor = null">
    <!-- Stat cards -->
    <div class="grid grid-cols-3 gap-4 mb-4 max-md:grid-cols-1">
      <div class="card">
        <div class="muted text-[.86rem]">Team members</div>
        <div class="text-[1.9rem] font-bold text-ink leading-tight mt-1">{{ members.length }}</div>
        <div class="muted text-[.82rem] mt-1">{{ adminCount }} admin, {{ staffCount }} staff</div>
      </div>
      <div class="card">
        <div class="muted text-[.86rem]">Active now</div>
        <div class="text-[1.9rem] font-bold text-ink leading-tight mt-1">0</div>
        <div class="muted text-[.82rem] mt-1">Scanning live</div>
      </div>
      <div class="card">
        <div class="muted text-[.86rem]">Avg conversion</div>
        <div class="text-[1.9rem] font-bold text-ink leading-tight mt-1">0<span class="text-[1.1rem]">%</span></div>
        <div class="muted text-[.82rem] mt-1">Across all reps</div>
      </div>
    </div>

    <div class="grid grid-cols-[1fr_340px] gap-4 items-start max-lg:grid-cols-1">
      <!-- Members table -->
      <div class="card">
        <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
          <SearchInput v-model="search" placeholder="Search" class="max-w-72 flex-1" />
          <button class="btn" @click.stop="inviteOpen = !inviteOpen">+ Invite member</button>
        </div>

        <!-- Invite form -->
        <div v-if="inviteOpen" class="border border-line rounded-xl p-3 mb-4 bg-[#f7f8fa]">
          <div class="flex gap-2.5 flex-wrap items-center">
            <input v-model="form.email" type="email" placeholder="Email" class="flex-[1_1_180px]">
            <input v-model="form.first_name" placeholder="First name" class="flex-[0_1_120px]">
            <input v-model="form.last_name" placeholder="Last name" class="flex-[0_1_120px]">
            <select v-model="form.role" class="py-[9px] px-3 rounded-[10px] border border-[#cbd5e1]">
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
            </select>
            <input v-model="form.password" type="password" placeholder="Password (enables login)" class="flex-[1_1_170px]">
            <button class="btn sm" :disabled="adding || !form.email" @click="add">{{ adding ? 'Adding…' : 'Invite' }}</button>
          </div>
          <p v-if="error" class="error mt-2">{{ error }}</p>
        </div>

        <table>
          <thead>
            <tr><th>Members</th><th>Role</th><th>Conversion rate</th><th class="text-right">Actions</th></tr>
          </thead>
          <tbody>
            <template v-for="m in paged" :key="m.id">
              <tr>
                <td>
                  <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-full bg-brand-soft text-brand grid place-items-center font-bold text-[.78rem] shrink-0">{{ initials(m.contact?.name, m.contact?.email) }}</span>
                    <div class="min-w-0">
                      <div class="font-semibold text-ink truncate">{{ m.contact?.name || m.contact?.email }} <span v-if="isSelf(m)" class="badge">you</span></div>
                      <div class="muted text-[.82rem] truncate">{{ m.contact?.email }}</div>
                    </div>
                  </div>
                </td>
                <td><span class="badge capitalize">{{ m.role }}</span></td>
                <td class="muted">View only</td>
                <td class="text-right">
                  <div class="relative inline-block" @click.stop>
                    <button class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:bg-[#f1f2f6]" @click="actionsFor = actionsFor === m.id ? null : m.id">
                      <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    </button>
                    <div v-if="actionsFor === m.id" class="absolute right-0 top-full mt-1 bg-white border border-line rounded-xl shadow-lg z-20 min-w-40 overflow-hidden">
                      <button v-if="m.contact?.can_login" class="w-full text-left px-4 py-2.5 text-[.86rem] hover:bg-[#f7f8fa]" @click="openReset(m)">Reset password</button>
                      <button v-if="!isSelf(m)" class="w-full text-left px-4 py-2.5 text-[.86rem] text-[#dc2626] hover:bg-[#fef2f2]" @click="remove(m)">Remove</button>
                    </div>
                  </div>
                </td>
              </tr>
              <tr v-if="pwFor === m.id">
                <td colspan="4">
                  <div class="flex gap-2 items-center py-1">
                    <input v-model="pwValue" type="password" placeholder="New password (min 8)" class="max-w-[260px]">
                    <button class="btn sm" :disabled="pwValue.length < 8" @click="savePassword(m)">Save password</button>
                    <button class="btn sm ghost" @click="pwFor = null">Cancel</button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
        <p v-if="!filtered.length" class="muted py-4">No team members yet.</p>

        <!-- Pagination -->
        <div v-if="filtered.length" class="flex items-center justify-end gap-4 mt-4 pt-3 border-t border-line text-[.84rem] text-muted flex-wrap">
          <div class="flex items-center gap-2">
            <span>Nb / page</span>
            <select v-model.number="perPage" class="py-1.5 px-2 rounded-lg border border-[#cbd5e1]">
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <span>Page</span>
            <select v-model.number="page" class="py-1.5 px-2 rounded-lg border border-[#cbd5e1]">
              <option v-for="n in totalPages" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
          <span>{{ rangeLabel }}</span>
          <div class="flex items-center gap-1.5">
            <button class="w-8 h-8 rounded-lg border border-line grid place-items-center disabled:opacity-40" :disabled="page <= 1" @click="page--">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <button class="w-8 h-8 rounded-lg border border-line grid place-items-center disabled:opacity-40" :disabled="page >= totalPages" @click="page++">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Staff permissions -->
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-ink m-0">Staff permissions</h3>
          <span class="muted text-[.8rem]">Per member</span>
        </div>

        <select v-model.number="selectedId" class="w-full py-2.5 px-3 rounded-[11px] border border-[#cbd5e1] mb-4 text-[.9rem]">
          <option :value="null">Select member…</option>
          <option v-for="m in selectableMembers" :key="m.id" :value="m.id">{{ m.contact?.name || m.contact?.email }}</option>
        </select>

        <p v-if="!selected" class="muted text-[.85rem]">Pick a member to set what they can do.</p>
        <p v-else-if="permRole === 'admin'" class="text-[.85rem] text-brand font-semibold mb-2">Admins have full access.</p>

        <div v-if="selected" class="flex flex-col">
          <div v-for="(p, i) in PERMS" :key="p.key" class="flex items-center justify-between py-3.5" :class="{ 'border-t border-line': i > 0 }">
            <span class="text-[.92rem] text-ink">{{ p.label }}</span>
            <button
              type="button"
              class="relative w-11 h-6 rounded-full shrink-0 transition-colors duration-150 disabled:opacity-50"
              :class="perms[p.key] ? 'bg-brand' : 'bg-[#cdd2dc]'"
              :disabled="permRole === 'admin' || savingPerms"
              @click="togglePerm(p.key)"
            >
              <span class="absolute top-[3px] left-[3px] w-[18px] h-[18px] rounded-full bg-white shadow-sm transition-transform duration-150" :class="perms[p.key] ? 'translate-x-5' : 'translate-x-0'" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
