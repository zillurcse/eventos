<script setup lang="ts">
const { eventId, draft, packages, error, saving, create, drawerMode } = useExhibitorContext()

function onLogoChange(v: string | string[] | null) {
  draft.logo_url = (Array.isArray(v) ? v[0] : v) || ''
}
function onLogoUploaded(v: { id: number, url: string }) {
  draft.logo_file_id = v.id
}

const packageOptions = computed(() => packages.value.map((pkg: any) => ({ value: pkg.id, label: pkg.name })))
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
        :gallery-path="`/events/${eventId}/gallery`"
        @update:model-value="onLogoChange"
        @uploaded="onLogoUploaded"
      />
    </div>

    <div class="flex flex-col gap-3">
      <AppInput v-model="draft.name" label="Exhibitor Name" placeholder="Enter the exhibitor Name" />

      <AppInput v-model="draft.email" type="email" label="Exhibitor Email" placeholder="Enter the exhibitor email" />

      <AppSelect v-model="draft.package_id" label="Package" placeholder="Select Package" :options="packageOptions" />

      <AppSelect v-model="draft.stall_no" label="Stall No" placeholder="Select Stall No" :options="STALL_OPTIONS" />

      <AppSelect v-model="draft.type" label="Type" placeholder="Select Type" :options="TYPE_OPTIONS" />
    </div>

    <!-- Flags row -->
    <div class="flex items-center gap-5 my-4">
      <AppCheckbox v-model="draft.rating" label="Rating" />
      <AppCheckbox v-model="draft.featured" label="Featured" />
      <AppCheckbox v-model="draft.premium" label="Premium" />
    </div>

    <!-- Contact details -->
    <div class="mt-4 mb-2">
      <p class="font-semibold text-[.92rem] text-ink m-0">Contact details <span class="muted font-normal">(For internal use only)</span></p>
    </div>

    <div class="flex flex-col gap-3">
      <AppInput v-model="draft.contact.full_name" label="Full name" placeholder="Enter Full name" />

      <AppInput v-model="draft.contact.company_name" label="Company name" placeholder="Enter Company name" />

      <AppInput v-model="draft.contact.position" label="Position" placeholder="Enter Position" />

      <AppInput v-model="draft.contact.email" type="email" label="Email address" placeholder="Enter Email address" />
    </div>

    <label class="mt-3 block">Mobile number</label>
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
