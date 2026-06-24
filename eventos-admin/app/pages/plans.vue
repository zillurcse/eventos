<script setup lang="ts">
definePageMeta({ middleware: 'platform', title: 'Plans', subtitle: 'Subscription tiers' })

const api = useApi()
const plans = ref<any[]>([])
const saved = ref<string | null>(null)

async function load() {
  plans.value = (await api<any>('/admin/plans')).data
}
async function save(p: any) {
  await api(`/admin/plans/${p.id}`, {
    method: 'PATCH',
    body: { price_cents: Number(p.price_cents), is_public: !!p.is_public },
  })
  saved.value = p.id
  setTimeout(() => (saved.value = null), 1500)
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <div v-for="p in plans" :key="p.id" class="card">
      <h2>{{ p.name }} <span class="badge">{{ p.slug }}</span> <span v-if="saved === p.id" class="badge active">saved ✓</span></h2>
      <div class="flex gap-3.5 items-center flex-wrap">
        <label>Price (cents): <input v-model="p.price_cents" type="number" class="w-[140px] inline-block" /></label>
        <label><input v-model="p.is_public" type="checkbox" class="w-auto" /> Public</label>
        <button class="btn sm" @click="save(p)">Save</button>
      </div>
      <p class="muted text-[.85rem]">Limits: {{ JSON.stringify(p.limits) }}</p>
    </div>
    <p v-if="!plans.length" class="muted">Loading plans…</p>
  </div>
</template>
