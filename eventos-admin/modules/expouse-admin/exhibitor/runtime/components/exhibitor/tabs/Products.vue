<script setup lang="ts">
const { productForm, subSaving, subError, products, addProduct, removeProduct } = useExhibitorContext()

const columns = [
  { key: 'name', label: 'Product' },
  { key: 'description', label: 'Description' },
  { key: 'price', label: 'Price', align: 'right' },
]
</script>

<template>
  <div>
    <!-- Add product form -->
    <div class="border border-line rounded-xl p-4 mb-5 bg-[#f7f8fa]">
      <p class="font-semibold text-[.92rem] m-0 mb-3 text-ink">Add a product</p>
      <div class="grid gap-2">
        <AppInput v-model="productForm.name" label="Product name" placeholder="Product name" />
        <AppInput v-model="productForm.description" label="Description" placeholder="Description" />
        <AppInput v-model="productForm.price" type="number" step="0.01" label="Price ($)" placeholder="0.00" />
      </div>
      <div class="flex justify-end mt-3">
        <button class="btn sm" :disabled="subSaving || !productForm.name" @click="addProduct">
          {{ subSaving ? 'ADDING…' : '+ ADD PRODUCT' }}
        </button>
      </div>
      <p v-if="subError" class="error mt-2 mb-0">{{ subError }}</p>
    </div>

    <!-- Products table -->
    <DataTable
      :items="products"
      :columns="columns"
      row-key="id"
      storage-key="exhibitor-products"
      empty-text="No products yet."
    >
      <template #cell-name="{ row }">
        <span class="font-semibold text-ink text-[.88rem]">{{ row.name }}</span>
      </template>
      <template #cell-description="{ row }">
        <span class="muted text-[.84rem]">{{ row.description || '—' }}</span>
      </template>
      <template #cell-price="{ row }">
        <span class="font-semibold text-ink">{{ exhibitorMoney(row.price_cents) }}</span>
      </template>
      <template #actions="{ row }">
        <button
          class="w-8 h-8 inline-flex items-center justify-center bg-transparent border-0 rounded-lg cursor-pointer text-muted hover:text-[#dc2626] hover:bg-[#fef2f2] transition-colors"
          title="Remove product"
          @click="removeProduct(row)"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
        </button>
      </template>
    </DataTable>
  </div>
</template>
