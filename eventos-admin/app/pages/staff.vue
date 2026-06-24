<script setup lang="ts">
definePageMeta({ middleware: 'platform', title: 'Platform staff', subtitle: 'Super-admins of the control plane' })

const api = useApi()
const auth = useAuthStore()

const users = ref<any[]>([])
const form = reactive({ name: '', email: '', password: '' })
const error = ref('')
const creating = ref(false)
const pwFor = ref<string | null>(null)
const pwValue = ref('')

const isSelf = (u: any) => u.email === auth.user?.email

async function load() {
  try { users.value = (await api<any>('/admin/users?type=platform')).data } catch { /* */ }
}

async function create() {
  error.value = ''
  creating.value = true
  try {
    await api('/admin/users', { method: 'POST', body: { ...form, is_platform_staff: true } })
    form.name = ''; form.email = ''; form.password = ''
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not create the account.'
  } finally {
    creating.value = false
  }
}

async function setStatus(u: any, status: string) {
  await api(`/admin/users/${u.id}/status`, { method: 'POST', body: { status } })
  await load()
}

async function remove(u: any) {
  if (!confirm(`Remove ${u.email}? They will lose access immediately.`)) return
  await api(`/admin/users/${u.id}`, { method: 'DELETE' })
  await load()
}

function openReset(u: any) {
  pwFor.value = pwFor.value === u.id ? null : u.id
  pwValue.value = ''
}
async function savePassword(u: any) {
  if (pwValue.value.length < 8) return
  await api(`/admin/users/${u.id}/password`, { method: 'POST', body: { password: pwValue.value } })
  pwFor.value = null; pwValue.value = ''
}

onMounted(load)
</script>

<template>
  <div>
    <div class="card">
      <h2>Add a super-admin</h2>
      <div class="flex gap-2.5 flex-wrap items-center">
        <input v-model="form.name" placeholder="Full name" class="flex-[1_1_160px]" />
        <input v-model="form.email" type="email" placeholder="Email" class="flex-[1_1_200px]" />
        <input v-model="form.password" type="password" placeholder="Password (min 8)" class="flex-[1_1_180px]" />
        <button class="btn" :disabled="creating" @click="create">{{ creating ? 'Creating…' : 'Create' }}</button>
      </div>
      <p v-if="error" class="error">{{ error }}</p>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr><th>Name</th><th>Email</th><th>Status</th><th>Last login</th><th /></tr>
        </thead>
        <tbody>
          <template v-for="u in users" :key="u.id">
            <tr>
              <td><strong>{{ u.name }}</strong> <span v-if="isSelf(u)" class="badge">you</span></td>
              <td>{{ u.email }}</td>
              <td><span class="badge" :class="u.status">{{ u.status }}</span></td>
              <td class="muted">{{ u.last_login_at ? new Date(u.last_login_at).toLocaleString() : 'never' }}</td>
              <td class="whitespace-nowrap">
                <button class="btn sm ghost" @click="openReset(u)">Reset PW</button>
                <template v-if="!isSelf(u)">
                  <button v-if="u.status !== 'disabled'" class="btn sm danger" @click="setStatus(u, 'disabled')">Disable</button>
                  <button v-else class="btn sm" @click="setStatus(u, 'active')">Enable</button>
                  <button class="btn sm danger" @click="remove(u)">Remove</button>
                </template>
              </td>
            </tr>
            <tr v-if="pwFor === u.id">
              <td colspan="5">
                <div class="flex gap-2 items-center">
                  <input v-model="pwValue" type="password" placeholder="New password (min 8)" class="max-w-[260px]" />
                  <button class="btn sm" :disabled="pwValue.length < 8" @click="savePassword(u)">Save password</button>
                  <button class="btn sm ghost" @click="pwFor = null">Cancel</button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      <p v-if="!users.length" class="muted">No platform staff found.</p>
    </div>
  </div>
</template>
