<script setup lang="ts">
const { productForm, subSaving, subError, products, addProduct, removeProduct } = useExhibitorContext()
</script>

<template>
  <div>
    <div class="border border-line rounded-xl p-4 mb-4">
      <p class="font-semibold text-[.92rem] m-0 mb-2">Add a product</p>
      <div class="flex flex-wrap gap-2 items-end">
        <AppInput v-model="productForm.name" placeholder="Product name" class="flex-[1_1_180px]" />
        <AppInput v-model="productForm.description" placeholder="Description" class="flex-[1_1_220px]" />
        <AppInput v-model="productForm.price" type="number" step="0.01" placeholder="Price ($)" class="flex-[0_1_120px]" />
        <button class="btn sm" :disabled="subSaving || !productForm.name" @click="addProduct">ADD</button>
      </div>
    </div>
    <table>
      <thead><tr><th>Product</th><th>Description</th><th>Price</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
        <tr v-for="pd in products" :key="pd.id">
          <td class="font-semibold text-ink">{{ pd.name }}</td>
          <td class="muted text-[.84rem]">{{ pd.description || '—' }}</td>
          <td>{{ exhibitorMoney(pd.price_cents) }}</td>
          <td class="text-right"><button class="bg-transparent border-0 cursor-pointer text-[#dc2626]" title="Remove" @click="removeProduct(pd)">🗑</button></td>
        </tr>
        <tr v-if="!products.length"><td colspan="4" class="muted text-center py-8">No products yet.</td></tr>
      </tbody>
    </table>
    <p v-if="subError" class="error mt-2">{{ subError }}</p>
  </div>
</template>
