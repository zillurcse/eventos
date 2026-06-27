<script setup lang="ts">
const { draft, packages, logoUploading, pickLogo, error, saving, create, drawerMode } = useExhibitorContext()
</script>

<template>
  <Drawer title="Add Exhibitor" @close="drawerMode = null">
    <!-- Logo uploader -->
    <div class="flex justify-center mb-5">
      <label class="relative cursor-pointer block" style="width:100%;max-width:285px;">
        <div class="w-full rounded-2xl overflow-hidden bg-[#e8eaed] flex items-center justify-center" style="height:155px;">
          <img v-if="draft.logo_url" :src="draft.logo_url" class="w-full h-full object-cover" alt="logo">
          <svg v-else viewBox="0 0 285 155" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
            <rect width="285" height="155" fill="#dde1e7"/>
            <ellipse cx="200" cy="110" rx="120" ry="60" fill="#7ec8c0"/>
            <ellipse cx="100" cy="120" rx="90" ry="50" fill="#5aa8a0"/>
            <circle cx="185" cy="55" r="28" fill="#f0b04a"/>
            <ellipse cx="75" cy="115" rx="70" ry="40" fill="#4a9890"/>
          </svg>
        </div>
        <div class="absolute inset-0 flex items-center justify-center">
          <div class="w-10 h-10 bg-white rounded-xl shadow-md flex items-center justify-center text-brand text-2xl font-light select-none">
            {{ logoUploading ? '…' : '+' }}
          </div>
        </div>
        <input type="file" accept="image/*" class="hidden" @change="pickLogo">
      </label>
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
