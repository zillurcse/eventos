<script setup lang="ts">
definePageMeta({ middleware: 'platform', title: 'Organizations', subtitle: 'Every tenant on the platform' })

const api = useApi()
const orgs = ref<any[]>([])

async function load() {
  try { orgs.value = (await api<any>('/admin/organizations')).data } catch { /* */ }
}
async function setStatus(o: any, status: string) {
  await api(`/admin/organizations/${o.id}`, { method: 'PATCH', body: { status } })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="card">
      <table>
        <thead>
          <tr><th>Name</th><th>Status</th><th>Events</th><th>Members</th><th>Contacts</th><th /></tr>
        </thead>
        <tbody>
          <tr v-for="o in orgs" :key="o.id">
            <td><strong>{{ o.name }}</strong><br><span class="muted text-[.8rem]">{{ o.slug }}</span></td>
            <td><span class="badge" :class="o.status">{{ o.status }}</span></td>
            <td>{{ o.events }}</td>
            <td>{{ o.members }}</td>
            <td>{{ o.contacts }}</td>
            <td>
              <button v-if="o.status !== 'suspended'" class="btn sm danger" @click="setStatus(o, 'suspended')">Suspend</button>
              <button v-else class="btn sm" @click="setStatus(o, 'active')">Activate</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!orgs.length" class="muted">No organizations yet.</p>
    </div>
  </div>
</template>
