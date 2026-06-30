<script setup lang="ts">
definePageMeta({ middleware: 'organizer', title: 'Team', subtitle: 'Who can manage your events' })

const auth = useAuthStore()
const api = useApi()

const members = ref<any[]>([])
const roles = ref<any[]>([])
const form = reactive({ email: '', name: '', password: '', role: '' })
const error = ref('')
const permError = ref(false)
const adding = ref(false)

const isSelf = (m: any) => m.user.email === auth.user?.email

async function load() {
  try {
    const [m, r] = await Promise.all([api<any>('/members'), api<any>('/assignable-roles')])
    members.value = m.data
    roles.value = r.data
    if (!form.role && roles.value.length) {
      form.role = String(roles.value.find((x: any) => x.name === 'manager')?.id ?? roles.value[0].id)
    }
  } catch (e: any) {
    if (e?.response?.status === 403) permError.value = true
  }
}

async function add() {
  error.value = ''
  adding.value = true
  try {
    await api('/members', {
      method: 'POST',
      body: {
        email: form.email,
        name: form.name || undefined,
        password: form.password || undefined,
        roles: form.role ? [Number(form.role)] : [],
      },
    })
    form.email = ''; form.name = ''; form.password = ''
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not add the member.'
  } finally {
    adding.value = false
  }
}

async function changeRole(m: any, roleId: string) {
  await api(`/members/${m.id}`, { method: 'PATCH', body: { roles: [Number(roleId)] } })
  await load()
}
async function setStatus(m: any, status: string) {
  await api(`/members/${m.id}`, { method: 'PATCH', body: { status } })
  await load()
}
async function remove(m: any) {
  if (!confirm(`Remove ${m.user.email} from the team?`)) return
  await api(`/members/${m.id}`, { method: 'DELETE' })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <div v-if="permError" class="card">
      <p class="muted">You don't have permission to manage the team. Ask an owner.</p>
    </div>

    <template v-else>
      <div class="card">
        <h2>Add a team member</h2>
        <div class="flex gap-2.5 flex-wrap items-center">
          <input v-model="form.email" type="email" placeholder="Email" class="flex-[1_1_200px]" />
          <input v-model="form.name" placeholder="Name (optional)" class="flex-[1_1_150px]" />
          <input v-model="form.password" type="password" placeholder="Password (optional)" class="flex-[1_1_160px]" />
          <select v-model="form.role" class="py-[9px] px-3 rounded-[10px] border border-[#cbd5e1]">
            <option v-for="r in roles" :key="r.id" :value="String(r.id)">{{ r.name }}</option>
          </select>
          <button class="btn" :disabled="adding || !form.email" @click="add">{{ adding ? 'Adding…' : 'Add' }}</button>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr><th>Member</th><th>Status</th><th>Role</th><th /></tr>
          </thead>
          <tbody>
            <tr v-for="m in members" :key="m.id">
              <td>
                <strong>{{ m.user.name }}</strong> <span v-if="isSelf(m)" class="badge">you</span>
                <br><span class="muted text-[.82rem]">{{ m.user.email }}</span>
              </td>
              <td><span class="badge" :class="m.status">{{ m.status }}</span></td>
              <td>
                <select :value="String(m.roles[0]?.id ?? '')" class="py-1.5 px-2.5 rounded-lg border border-[#cbd5e1]" @change="changeRole(m, ($event.target as HTMLSelectElement).value)">
                  <option v-for="r in roles" :key="r.id" :value="String(r.id)">{{ r.name }}</option>
                </select>
              </td>
              <td class="whitespace-nowrap">
                <template v-if="!isSelf(m)">
                  <button v-if="m.status !== 'suspended'" class="btn sm danger" @click="setStatus(m, 'suspended')">Suspend</button>
                  <button v-else class="btn sm" @click="setStatus(m, 'active')">Activate</button>
                  <button class="btn sm danger" @click="remove(m)">Remove</button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!members.length" class="muted">No team members yet.</p>
      </div>
    </template>
  </div>
</template>
