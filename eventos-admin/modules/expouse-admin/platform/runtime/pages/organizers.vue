<script setup lang="ts">
definePageMeta({ middleware: 'platform', title: 'Organizers', subtitle: 'Manage org members and roles' })

const api = useApi()

const orgs = ref<any[]>([])
const roles = ref<any[]>([])
const members = ref<any[]>([])
const orgId = ref<string>('')          // organization uuid
const error = ref('')
const adding = ref(false)
const form = reactive({ email: '', name: '', password: '', role: '' })
const pwFor = ref<string | null>(null)
const pwValue = ref('')

async function loadOrgs() {
  try { orgs.value = (await api<any>('/admin/organizations')).data } catch { /* */ }
}

async function loadOrg() {
  if (!orgId.value) { members.value = []; return }
  const [m, r] = await Promise.all([
    api<any>(`/admin/organizations/${orgId.value}/members`),
    api<any>(`/admin/roles?organization=${orgId.value}`),
  ])
  members.value = m.data
  roles.value = r.data
  if (!form.role && roles.value.length) form.role = String(roles.value.find((x: any) => x.name === 'manager')?.id ?? roles.value[0].id)
}

async function add() {
  error.value = ''
  adding.value = true
  try {
    await api(`/admin/organizations/${orgId.value}/members`, {
      method: 'POST',
      body: {
        email: form.email,
        name: form.name || undefined,
        password: form.password || undefined,
        roles: form.role ? [Number(form.role)] : [],
      },
    })
    form.email = ''; form.name = ''; form.password = ''
    await loadOrg()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not add the organizer.'
  } finally {
    adding.value = false
  }
}

async function setStatus(m: any, status: string) {
  await api(`/admin/organizations/${orgId.value}/members/${m.id}`, { method: 'PATCH', body: { status } })
  await loadOrg()
}
async function changeRole(m: any, roleId: string) {
  await api(`/admin/organizations/${orgId.value}/members/${m.id}`, { method: 'PATCH', body: { roles: [Number(roleId)] } })
  await loadOrg()
}
async function remove(m: any) {
  if (!confirm(`Remove ${m.user.email} from this organization?`)) return
  await api(`/admin/organizations/${orgId.value}/members/${m.id}`, { method: 'DELETE' })
  await loadOrg()
}
function openReset(m: any) {
  pwFor.value = pwFor.value === m.id ? null : m.id
  pwValue.value = ''
}
async function savePassword(m: any) {
  if (pwValue.value.length < 8) return
  await api(`/admin/users/${m.user.id}/password`, { method: 'POST', body: { password: pwValue.value } })
  pwFor.value = null; pwValue.value = ''
}

watch(orgId, loadOrg)
onMounted(loadOrgs)
</script>

<template>
  <div>
    <div class="card">
      <label>Organization:
        <select v-model="orgId" class="py-[9px] px-3 rounded-[10px] border border-[#cbd5e1] ml-2">
          <option value="">— select —</option>
          <option v-for="o in orgs" :key="o.id" :value="o.id">{{ o.name }}</option>
        </select>
      </label>
    </div>

    <template v-if="orgId">
      <div class="card">
        <h2>Add an organizer</h2>
        <div class="flex gap-2.5 flex-wrap items-center">
          <input v-model="form.email" type="email" placeholder="Email" class="flex-[1_1_200px]" />
          <input v-model="form.name" placeholder="Name (optional)" class="flex-[1_1_150px]" />
          <input v-model="form.password" type="password" placeholder="Password (optional)" class="flex-[1_1_160px]" />
          <select v-model="form.role" class="py-[9px] px-3 rounded-[10px] border border-[#cbd5e1]">
            <option v-for="r in roles" :key="r.id" :value="String(r.id)">{{ r.name }}</option>
          </select>
          <button class="btn" :disabled="adding || !form.email" @click="add">{{ adding ? 'Adding…' : 'Add' }}</button>
        </div>
        <p class="muted text-[.82rem]">If no password is set, the account is created without a usable login until a password is set.</p>
        <p v-if="error" class="error">{{ error }}</p>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr><th>Member</th><th>Status</th><th>Role</th><th /></tr>
          </thead>
          <tbody>
            <template v-for="m in members" :key="m.id">
              <tr>
                <td><strong>{{ m.user.name }}</strong><br><span class="muted text-[.82rem]">{{ m.user.email }}</span></td>
                <td><span class="badge" :class="m.status">{{ m.status }}</span></td>
                <td>
                  <select :value="String(m.roles[0]?.id ?? '')" class="py-[6px] px-[10px] rounded-lg border border-[#cbd5e1]" @change="changeRole(m, ($event.target as HTMLSelectElement).value)">
                    <option v-for="r in roles" :key="r.id" :value="String(r.id)">{{ r.name }}</option>
                  </select>
                </td>
                <td class="whitespace-nowrap">
                  <button class="btn sm ghost" @click="openReset(m)">Reset PW</button>
                  <button v-if="m.status !== 'suspended'" class="btn sm danger" @click="setStatus(m, 'suspended')">Suspend</button>
                  <button v-else class="btn sm" @click="setStatus(m, 'active')">Activate</button>
                  <button class="btn sm danger" @click="remove(m)">Remove</button>
                </td>
              </tr>
              <tr v-if="pwFor === m.id">
                <td colspan="4">
                  <div class="flex gap-2 items-center">
                    <input v-model="pwValue" type="password" placeholder="New password (min 8)" class="max-w-[260px]" />
                    <button class="btn sm" :disabled="pwValue.length < 8" @click="savePassword(m)">Save password</button>
                    <button class="btn sm ghost" @click="pwFor = null">Cancel</button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
        <p v-if="!members.length" class="muted">No organizers in this organization yet.</p>
      </div>
    </template>
  </div>
</template>
