<script setup lang="ts">
const { draft, packages, error, saving, create, drawerMode } = useExhibitorContext()

function onLogoChange(v: string | string[] | null) {
  draft.logo_url = (Array.isArray(v) ? v[0] : v) || ''
}
function onLogoUploaded(v: { id: number, url: string }) {
  draft.logo_file_id = v.id
}
</script>

<template>
  <Drawer title="Add Exhibitor" @close="drawerMode = null">
    <!-- Logo uploader -->
    <div class="flex justify-center mb-5">
      <ImageField
        :model-value="draft.logo_url || null"
        :aspect="285 / 155"
        :output-width="570"
        :output-height="310"
        collection="exhibitor_logo"
        card-width="285px"
        hint="285×155px recommended"
        @update:model-value="onLogoChange"
        @uploaded="onLogoUploaded"
      />
    </div>

    <label>Exhibitor Name</label>
    <input v-model="draft.name" placeholder="Enter the exhibitor Name">

    <label>Exhibitor Email</label>
    <input v-model="draft.email" type="email" placeholder="Enter the exhibitor email">

    <label>Package</label>
    <select v-model="draft.package_id">
      <option value="">Select Package</option>
      <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
    </select>

    <label>Stall No</label>
    <select v-model="draft.stall_no">
      <option value="">Select Stall No</option>
      <option v-for="s in STALL_OPTIONS" :key="s" :value="s">{{ s }}</option>
    </select>

    <label>Type</label>
    <select v-model="draft.type">
      <option value="">Select Type</option>
      <option v-for="t in TYPE_OPTIONS" :key="t" :value="t">{{ t }}</option>
    </select>

    <!-- Flags row -->
    <div class="flex items-center gap-5 my-3">
      <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
        <input v-model="draft.rating" type="checkbox" class="w-4.25 h-4.25 m-0 accent-brand"> Rating
      </label>
      <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
        <input v-model="draft.featured" type="checkbox" class="w-4.25 h-4.25 m-0 accent-brand"> Featured
      </label>
      <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
        <input v-model="draft.premium" type="checkbox" class="w-4.25 h-4.25 m-0 accent-brand"> Premium
      </label>
    </div>

    <!-- Contact details -->
    <div class="mt-4 mb-2">
      <p class="font-semibold text-[.92rem] text-ink m-0">Contact details <span class="muted font-normal">(For internal use only)</span></p>
    </div>

    <label>Full name</label>
    <input v-model="draft.contact.full_name" placeholder="Enter Full name">

    <label>Company name</label>
    <input v-model="draft.contact.company_name" placeholder="Enter Company name">

    <label>Position</label>
    <input v-model="draft.contact.position" placeholder="Enter Position">

    <label>Email address</label>
    <input v-model="draft.contact.email" type="email" placeholder="Enter Email address">

    <label>Mobile number</label>
    <div class="flex items-center rounded-[11px] overflow-hidden border border-[#d7dae1] my-1.5 bg-white focus-within:border-brand" style="transition:border-color .15s">
      <select v-model="draft.contact.phone_code" style="border:0;box-shadow:none;margin:0;border-radius:0;background:#f7f8fa;width:auto;padding:10px 8px;border-right:1px solid #d7dae1;cursor:pointer;">
        <option v-for="p in PHONE_CODES" :key="p.code" :value="p.code">{{ p.flag }} {{ p.code }}</option>
      </select>
      <input v-model="draft.contact.phone" type="tel" placeholder="Enter a phone number" style="border:0;box-shadow:none;margin:0;border-radius:0;flex:1;outline:none;">
    </div>

    <p v-if="error" class="error mt-2">{{ error }}</p>

    <div class="pt-4 mt-2">
      <button class="btn w-full py-3 text-[.95rem] tracking-widest" :disabled="saving || !draft.name.trim()" @click="create">
        {{ saving ? 'CREATING…' : 'CREATE' }}
      </button>
    </div>
  </Drawer>
</template>
