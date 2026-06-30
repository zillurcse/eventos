<script setup lang="ts">
definePageMeta({ middleware: 'platform', title: 'Exhibitors & sponsors', subtitle: 'Exhibitor accounts across all tenants' })

const api = useApi()

const exhibitors = ref<any[]>([])
const filters = reactive({ q: '', type: '', status: '' })
const loginFor = ref<string | null>(null)
const loginForm = reactive({ email: '', first_name: '', last_name: '', password: '' })
const error = ref('')

async function load() {
  const qs = new URLSearchParams(
    Object.entries(filters).filter(([, v]) => v) as [string, string][],
  ).toString()
  try { exhibitors.value = (await api<any>(`/admin/exhibitors${qs ? '?' + qs : ''}`)).data } catch { /* */ }
}

async function setStatus(p: any, status: string) {
  await api(`/admin/exhibitors/${p.id}`, { method: 'PATCH', body: { status } })
  await load()
}

function openLogin(p: any) {
  loginFor.value = loginFor.value === p.id ? null : p.id
  loginForm.email = p.admin_email || ''
  loginForm.first_name = ''; loginForm.last_name = ''; loginForm.password = ''
  error.value = ''
}
async function saveLogin(p: any) {
  error.value = ''
  try {
    await api(`/admin/exhibitors/${p.id}/admin`, { method: 'POST', body: { ...loginForm } })
    loginFor.value = null
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not set the exhibitor login.'
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="card">
      <div class="flex gap-2.5 flex-wrap items-center">
        <input v-model="filters.q" placeholder="Search name" class="flex-[1_1_180px]" @keyup.enter="load" />
        <select v-model="filters.type" class="py-[9px] px-3 rounded-[10px] border border-[#cbd5e1]">
          <option value="">All types</option>
          <option value="exhibitor">Exhibitor</option>
          <option value="sponsor">Sponsor</option>
        </select>
        <select v-model="filters.status" class="py-[9px] px-3 rounded-[10px] border border-[#cbd5e1]">
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
        </select>
        <button class="btn" @click="load">Filter</button>
      </div>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr><th>Exhibitor</th><th>Type</th><th>Organization</th><th>Event</th><th>Status</th><th>Admin login</th><th /></tr>
        </thead>
        <tbody>
          <template v-for="p in exhibitors" :key="p.id">
            <tr>
              <td><strong>{{ p.name }}</strong></td>
              <td><span class="badge">{{ p.type }}</span></td>
              <td>{{ p.organization }}</td>
              <td class="muted">{{ p.event }}</td>
              <td><span class="badge" :class="p.status">{{ p.status }}</span></td>
              <td>
                <span v-if="p.has_admin_login" class="muted text-[.82rem]">{{ p.admin_email }}</span>
                <span v-else class="muted">—</span>
              </td>
              <td class="whitespace-nowrap">
                <button class="btn sm ghost" @click="openLogin(p)">{{ p.has_admin_login ? 'Reset login' : 'Set login' }}</button>
                <button v-if="p.status !== 'suspended'" class="btn sm danger" @click="setStatus(p, 'suspended')">Suspend</button>
                <button v-else class="btn sm" @click="setStatus(p, 'active')">Activate</button>
              </td>
            </tr>
            <tr v-if="loginFor === p.id">
              <td colspan="7">
                <div class="flex gap-2 items-center flex-wrap">
                  <input v-model="loginForm.email" type="email" placeholder="Login email" class="max-w-[220px]" />
                  <input v-model="loginForm.first_name" placeholder="First name" class="max-w-[140px]" />
                  <input v-model="loginForm.last_name" placeholder="Last name" class="max-w-[140px]" />
                  <input v-model="loginForm.password" type="password" placeholder="Password (min 8)" class="max-w-[180px]" />
                  <button class="btn sm" :disabled="!loginForm.email || loginForm.password.length < 8" @click="saveLogin(p)">Save login</button>
                  <button class="btn sm ghost" @click="loginFor = null">Cancel</button>
                </div>
                <p v-if="error" class="error">{{ error }}</p>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      <p v-if="!exhibitors.length" class="muted">No exhibitors found.</p>
    </div>
  </div>
</template>
