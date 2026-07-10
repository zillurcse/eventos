<script setup lang="ts">
definePageMeta({ middleware: 'exhibitor', feature: 'products', title: 'Products', subtitle: 'Showcase what your booth offers' })

const api = useApi()
const products = ref<any[]>([])
const form = reactive({ name: '', description: '', price: '' })
const suspended = ref(false)
const error = ref('')
const creating = ref(false)

async function load() {
  try {
    products.value = (await api<any>('/exhibitor/products')).data
  } catch (e: any) {
    if (e?.response?.status === 403) suspended.value = true
  }
}

async function remove(p: any) {
  if (!confirm(`Remove "${p.name}"?`)) return
  await api(`/exhibitor/products/${p.id}`, { method: 'DELETE' })
  await load()
}

async function create() {
  error.value = ''
  creating.value = true
  try {
    await api('/exhibitor/products', {
      method: 'POST',
      body: {
        name: form.name,
        description: form.description || undefined,
        price_cents: form.price ? Math.round(Number(form.price) * 100) : undefined,
      },
    })
    form.name = ''; form.description = ''; form.price = ''
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not add the product.'
  } finally {
    creating.value = false
  }
}

function money(cents: number | null) {
  return cents != null ? '$' + (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'
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
        <h2>Add a product</h2>
        <div class="flex gap-2.5 flex-wrap items-center">
          <input v-model="form.name" placeholder="Product name" class="flex-[1_1_200px]" />
          <input v-model="form.description" placeholder="Description" class="flex-[1_1_220px]" />
          <input v-model="form.price" type="number" step="0.01" placeholder="Price ($)" class="flex-[0_1_130px]" />
          <button class="btn" :disabled="creating || !form.name" @click="create">{{ creating ? 'Adding…' : 'Add' }}</button>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr><th>Product</th><th>Description</th><th>Price</th><th /></tr>
          </thead>
          <tbody>
            <tr v-for="p in products" :key="p.id">
              <td><strong>{{ p.name }}</strong></td>
              <td class="muted">{{ p.description || '—' }}</td>
              <td>{{ money(p.price_cents) }}</td>
              <td class="whitespace-nowrap"><button class="btn sm danger" @click="remove(p)">Remove</button></td>
            </tr>
          </tbody>
        </table>
        <p v-if="!products.length" class="muted">No products yet.</p>
      </div>
    </template>
  </div>
</template>
