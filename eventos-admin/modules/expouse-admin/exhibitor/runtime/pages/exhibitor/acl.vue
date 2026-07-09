<script setup lang="ts">
definePageMeta({ middleware: 'exhibitor', title: 'Access Control', subtitle: 'Choose what each teammate can manage' })

const api = useApi()

const MODULES = [
  { key: 'products', label: 'Products' },
  { key: 'documents', label: 'Documents' },
  { key: 'projects', label: 'Projects' },
  { key: 'leads', label: 'Leads' },
  { key: 'meetings', label: 'Meetings' },
]

interface Row {
  id: number
  name: string
  email: string
  role: 'admin' | 'staff'
  lead: boolean
  perms: Record<string, boolean>
  saving: boolean
  saved: boolean
}

const rows = ref<Row[]>([])
const suspended = ref(false)

function toRow(m: any): Row {
  const perms: Record<string, boolean> = {}
  for (const mod of MODULES) perms[mod.key] = !!m.permissions?.[mod.key]
  return {
    id: m.id,
    name: m.contact?.name || m.contact?.email || 'Member',
    email: m.contact?.email || '',
    role: m.role === 'admin' ? 'admin' : 'staff',
    lead: !!m.is_lead_capturer,
    perms,
    saving: false,
    saved: false,
  }
}

async function load() {
  try {
    rows.value = (await api<any>('/exhibitor/members')).data.map(toRow)
  } catch (e: any) {
    if (e?.response?.status === 403) suspended.value = true
  }
}

async function save(row: Row) {
  row.saving = true
  row.saved = false
  try {
    await api(`/exhibitor/members/${row.id}`, {
      method: 'PATCH',
      body: { role: row.role, is_lead_capturer: row.lead, permissions: row.perms },
    })
    row.saved = true
    setTimeout(() => (row.saved = false), 1800)
  } finally {
    row.saving = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

    <template v-else>
      <div v-if="!rows.length" class="card"><p class="muted">No team members yet. Invite them from the Team page first.</p></div>

      <div v-for="row in rows" :key="row.id" class="card">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <strong class="text-ink">{{ row.name }}</strong>
            <span class="muted text-[.82rem] block">{{ row.email }}</span>
          </div>
          <div class="flex items-center gap-2">
            <select v-model="row.role" class="py-[8px] px-3 rounded-[10px] border border-[#cbd5e1] text-[.85rem]">
              <option value="admin">Admin</option>
              <option value="staff">Staff</option>
            </select>
            <button class="btn sm" :disabled="row.saving" @click="save(row)">
              {{ row.saving ? 'Saving…' : row.saved ? 'Saved ✓' : 'Save' }}
            </button>
          </div>
        </div>

        <!-- Admins implicitly have every module. -->
        <p v-if="row.role === 'admin'" class="mt-3 text-[.85rem] text-brand font-semibold">
          Full access — admins can manage everything.
        </p>

        <div v-else class="mt-3 flex flex-wrap gap-x-6 gap-y-2">
          <label v-for="mod in MODULES" :key="mod.key" class="flex items-center gap-2 text-[.88rem] text-ink cursor-pointer">
            <input v-model="row.perms[mod.key]" type="checkbox" class="w-4 h-4 accent-brand">
            {{ mod.label }}
          </label>
          <label class="flex items-center gap-2 text-[.88rem] text-ink cursor-pointer">
            <input v-model="row.lead" type="checkbox" class="w-4 h-4 accent-brand">
            Lead capturer
          </label>
        </div>
      </div>
    </template>
  </div>
</template>
