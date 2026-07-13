<script setup lang="ts">
const { productForm, subSaving, subError, products, addProduct, removeProduct } = useExhibitorContext()

const columns = [
  { key: 'name', label: 'Product' },
  { key: 'description', label: 'Description' },
  // `as const` keeps this a literal 'right' rather than widening to string,
  // which is what DataTable's column type expects.
  { key: 'price', label: 'Price', align: 'right' as const },
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
        <ExhibitorRowDeleteButton title="Remove product" @click="removeProduct(row)" />
      </template>
    </DataTable>
  </div>
</template>
