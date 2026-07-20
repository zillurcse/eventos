<script setup lang="ts">
const { productForm, subSaving, subError, products, addProduct, removeProduct } = useExhibitorContext()
const { upload } = useUpload()

const showAdd = ref(false)
const uploadingAttachment = ref(false)

function openAdd() {
  // productForm is shared with the collection (which blanks it after a save);
  // reset here too so a cancelled draft never leaks into the next open.
  Object.assign(productForm, PRODUCT_FORM)
  subError.value = ''
  showAdd.value = true
}

function onImageChange(v: string | string[] | null) {
  productForm.image_url = (Array.isArray(v) ? v[0] : v) || ''
}
function onImageUploaded(v: { id: number, url: string }) {
  productForm.image_file_id = v.id
}

async function onAttachment(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  uploadingAttachment.value = true
  try {
    const d = await upload(file, { collection: 'document' })
    productForm.attachment_url = d.url
    productForm.attachment_file_id = d.id
    productForm.attachment_name = file.name
  } catch {
    subError.value = 'Could not upload the attachment.'
  } finally {
    uploadingAttachment.value = false
    input.value = '' // allow re-selecting the same file
  }
}

async function submit() {
  if (!productForm.name.trim()) return
  await addProduct()
  if (!subError.value) showAdd.value = false
}

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
    <div class="flex items-center justify-between gap-3 mb-4">
      <p class="font-semibold text-[.92rem] m-0 text-ink">Products</p>
      <button class="btn sm" @click="openAdd">+ ADD PRODUCT</button>
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
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-lg overflow-hidden shrink-0 bg-brand-soft flex items-center justify-center">
            <img v-if="row.meta?.image_url" :src="row.meta.image_url" class="w-full h-full object-cover" :alt="row.name">
            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-4 h-4 text-brand"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
          </div>
          <div class="min-w-0">
            <div class="font-semibold text-ink text-[.88rem] truncate">{{ row.name }}</div>
            <span v-if="row.meta?.is_job_offer" class="badge mt-0.5">Job Offer</span>
          </div>
        </div>
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

    <!-- Add Product drawer -->
    <Drawer v-if="showAdd" title="Add Product" back @close="showAdd = false" @back="showAdd = false">
      <!-- Product Image -->
      <label class="block mb-1.5">Product Image</label>
      <ImageField
        :model-value="productForm.image_url || null"
        :aspect="1"
        collection="exhibitor_logo"
        card-width="96px"
        @update:model-value="onImageChange"
        @uploaded="onImageUploaded"
      />

      <div class="mt-4">
        <AppInput v-model="productForm.name" label="Product Title" placeholder="Enter Product Title" />
      </div>

      <div class="mt-4">
        <label class="block mb-1.5">Product Details</label>
        <textarea
          v-model="productForm.description"
          rows="5"
          placeholder="Enter Product Details"
          class="w-full bg-white border border-[#d7dae1] rounded-[11px] px-[13px] py-2.5 text-[.92rem] text-ink outline-none focus:border-brand resize-y"
        />
      </div>

      <div class="mt-4">
        <AppInput v-model="productForm.button_label" label="Button Label" placeholder="Enter Button Label" />
      </div>

      <div class="mt-4">
        <AppInput v-model="productForm.button_url" label="Button URL" placeholder="Enter Button URL" />
      </div>

      <!-- Attachment -->
      <div class="mt-4">
        <label class="block mb-1.5">Attachment</label>
        <div class="flex items-center gap-3 border border-[#d7dae1] rounded-[11px] px-2 py-2 bg-white">
          <label class="btn sm ghost cursor-pointer m-0 shrink-0">
            {{ uploadingAttachment ? 'Uploading…' : 'Choose File' }}
            <input type="file" class="hidden" @change="onAttachment">
          </label>
          <span class="text-[.88rem] truncate" :class="productForm.attachment_name ? 'text-ink' : 'muted'">
            {{ productForm.attachment_name || 'No File Chosen' }}
          </span>
        </div>
      </div>

      <!-- Is Job Offer -->
      <div class="mt-4">
        <AppCheckbox v-model="productForm.is_job_offer" label="Is Job Offer" />
      </div>

      <p v-if="subError" class="error mt-3 mb-0">{{ subError }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-6">
        <button
          class="btn"
          :disabled="subSaving || uploadingAttachment || !productForm.name.trim()"
          @click="submit"
        >
          {{ subSaving ? 'Adding…' : 'Add Product' }}
        </button>
        <button class="btn ghost" @click="showAdd = false">Cancel</button>
      </div>
    </Drawer>
  </div>
</template>
