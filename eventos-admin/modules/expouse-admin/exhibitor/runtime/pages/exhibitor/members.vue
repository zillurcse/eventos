<script setup lang="ts">
definePageMeta({ middleware: 'exhibitor', title: 'Team', subtitle: 'Your booth staff and admins' })

const auth = useAuthStore()
const api = useApi()

const members = ref<any[]>([])
const form = reactive({ email: '', first_name: '', last_name: '', role: 'staff', password: '' })
const suspended = ref(false)
const error = ref('')
const adding = ref(false)
const pwFor = ref<number | null>(null)
const pwValue = ref('')

const isSelf = (m: any) => m.contact?.email === auth.user?.email

async function load() {
  try {
    members.value = (await api<any>('/exhibitor/members')).data
  } catch (e: any) {
    if (e?.response?.status === 403) suspended.value = true
  }
}

async function add() {
  error.value = ''
  adding.value = true
  try {
    await api('/exhibitor/members', { method: 'POST', body: { ...form } })
    form.email = ''; form.first_name = ''; form.last_name = ''; form.password = ''; form.role = 'staff'
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not add the member.'
  } finally {
    adding.value = false
  }
}

async function remove(m: any) {
  if (!confirm(`Remove ${m.contact?.email}?`)) return
  await api(`/exhibitor/members/${m.id}`, { method: 'DELETE' })
  await load()
}

function openReset(m: any) {
  pwFor.value = pwFor.value === m.id ? null : m.id
  pwValue.value = ''
}
async function savePassword(m: any) {
  if (pwValue.value.length < 8) return
  await api(`/exhibitor/members/${m.id}/password`, { method: 'POST', body: { password: pwValue.value } })
  pwFor.value = null; pwValue.value = ''
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <div v-if="suspended" class="card">
      <p class="error">This exhibitor account is suspended.</p>
    </div>

    <template v-else>
      <div class="card">
        <h2>Invite a member</h2>
        <div class="flex gap-2.5 flex-wrap items-center">
          <input v-model="form.email" type="email" placeholder="Email" class="flex-[1_1_200px]" />
          <input v-model="form.first_name" placeholder="First name" class="flex-[0_1_130px]" />
          <input v-model="form.last_name" placeholder="Last name" class="flex-[0_1_130px]" />
          <select v-model="form.role" class="py-[9px] px-3 rounded-[10px] border border-[#cbd5e1]">
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
          </select>
          <input v-model="form.password" type="password" placeholder="Password (to enable login)" class="flex-[1_1_180px]" />
          <button class="btn" :disabled="adding || !form.email" @click="add">{{ adding ? 'Adding…' : 'Invite' }}</button>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr><th>Member</th><th>Role</th><th>Login</th><th /></tr>
          </thead>
          <tbody>
            <template v-for="m in members" :key="m.id">
              <tr>
                <td>
                  <strong>{{ m.contact?.name || m.contact?.email }}</strong> <span v-if="isSelf(m)" class="badge">you</span>
                  <br><span class="muted text-[.82rem]">{{ m.contact?.email }}</span>
                </td>
                <td><span class="badge">{{ m.role }}</span></td>
                <td>
                  <span v-if="m.contact?.can_login" class="badge active">can sign in</span>
                  <span v-else class="muted">no login</span>
                </td>
                <td class="whitespace-nowrap">
                  <button v-if="m.contact?.can_login" class="btn sm ghost" @click="openReset(m)">Reset PW</button>
                  <button v-if="!isSelf(m)" class="btn sm danger" @click="remove(m)">Remove</button>
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
        <p v-if="!members.length" class="muted">No team members yet.</p>
      </div>
    </template>
  </div>
</template>
