<script setup lang="ts">
// Full-page "Basic Details" panel for the exhibitor editor. Same context/draft
// as the add drawer's Details tab, laid out as a wide two-column form to match
// the design reference.
const {
  eventId, draft, packages,
  spotlightUploading, pickSpotlight,
  tagInput, addTag, removeTag,
  error, saving, update,
} = useExhibitorContext()

const listPath = computed(() => `/org/events/${eventId}/showcase/exhibitors`)

function onLogoChange(v: string | string[] | null) {
  draft.logo_url = (Array.isArray(v) ? v[0] : v) || ''
}
function onLogoUploaded(v: { id: number, url: string }) {
  draft.logo_file_id = v.id
}
function onSpotlightChange(v: string | string[] | null) {
  draft.spotlight_url = (Array.isArray(v) ? v[0] : v) || ''
}
function onSpotlightUploaded(v: { id: number, url: string }) {
  draft.spotlight_file_id = v.id
}

const packageOptions = computed(() => packages.value.map(pkg => ({ value: pkg.id, label: pkg.name })))

async function save() {
  const ok = await update()
  if (ok) navigateTo(listPath.value)
}
</script>

<template>
  <div>
    <div class="mb-6">
      <h2 class="text-[1.05rem] font-bold text-ink m-0">Basic Details</h2>
      <p class="muted text-[.85rem] mt-0.5 mb-0">Basic Details of the exhibitor.</p>
    </div>

    <!-- Logo -->
    <div class="mb-5">
      <label class="block mb-1.5">Image</label>
      <ImageField
        :model-value="draft.logo_url || null"
        :aspect="1"
        collection="exhibitor_logo"
        card-width="112px"
        :gallery-path="`/events/${eventId}/gallery`"
        @update:model-value="onLogoChange"
        @uploaded="onLogoUploaded"
      />
    </div>

    <!-- Core fields -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
      <AppInput v-model="draft.name" label="Exhibitor Name" placeholder="Enter Exhibitor Name" />
      <AppSelect v-model="draft.package_id" label="Package" placeholder="Select Package" :options="packageOptions" />

      <AppInput v-model="draft.email" type="email" label="Exhibitor Email" disabled placeholder="—" hint="The admin login email can't be changed after creation." />
      <div>
        <label class="block mb-1.5">Mobile No</label>
        <div class="flex items-center rounded-[11px] overflow-hidden border border-[#d7dae1] bg-white focus-within:border-brand">
          <select v-model="draft.phone_code" style="border:0;box-shadow:none;margin:0;border-radius:0;background:#f7f8fa;width:auto;padding:11px 8px;border-right:1px solid #d7dae1;cursor:pointer;">
            <option v-for="p in PHONE_CODES" :key="p.code" :value="p.code">{{ p.flag }} {{ p.code }}</option>
          </select>
          <input v-model="draft.phone" type="tel" placeholder="Enter Mobile No" style="border:0;box-shadow:none;margin:0;border-radius:0;flex:1;outline:none;padding:11px 13px;">
        </div>
      </div>

      <AppSelect v-model="draft.stall_no" label="Stall No" placeholder="Select Stall No" :options="STALL_OPTIONS" />
      <AppSelect v-model="draft.type" label="Type" placeholder="Select Type" :options="TYPE_OPTIONS" />
    </div>

    <!-- About -->
    <div class="mt-4">
      <label class="block mb-1.5">About</label>
      <SessionDescriptionEditor v-model="draft.about" :toolbar="['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link']" />
    </div>

    <!-- Website -->
    <div class="mt-4">
      <AppInput v-model="draft.website_url" label="Website" placeholder="https://" />
    </div>

    <!-- Custom Tags -->
    <div class="mt-4">
      <label class="block mb-1.5">Custom Tags</label>
      <div class="border border-line rounded-[11px] px-3 pt-2 pb-1.5 bg-white flex flex-wrap gap-1.5 min-h-11">
        <span v-for="tag in draft.tags" :key="tag" class="inline-flex items-center gap-1 bg-brand-soft text-brand-dark text-[.8rem] font-semibold px-2.5 py-0.5 rounded-full">
          {{ tag }}
          <button type="button" class="border-0 bg-transparent cursor-pointer text-brand-dark font-bold leading-none p-0" @click="removeTag(tag)">×</button>
        </span>
        <input v-model="tagInput" placeholder="Add tag & press enter" style="border:0;box-shadow:none;margin:0;padding:0;flex:1;min-width:120px;outline:none;background:transparent;" @keydown="addTag">
      </div>
    </div>

    <!-- Manage Filters -->
    <div class="mt-4">
      <label class="block mb-1.5">Mange Fliters</label>
      <ExhibitorFilterPicker />
    </div>

    <!-- Location -->
    <div class="border-t border-line mt-7 pt-5">
      <h3 class="text-base font-bold text-ink m-0 mb-4">Location</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
        <AppInput v-model="draft.venue" label="Venue" placeholder="Enter Venue" />
        <AppInput v-model="draft.street" label="Street" placeholder="Enter Street" />
        <AppInput v-model="draft.address_line1" label="Address Line 1" placeholder="Enter Address Line 1" />
        <AppInput v-model="draft.address_line2" label="Address Line 2" placeholder="Enter Address Line 2" />
        <AppSelect v-model="draft.country" label="Country" placeholder="Select Country" :options="COUNTRIES" />
        <AppInput v-model="draft.state" label="State" placeholder="Enter State" />
        <AppInput v-model="draft.city" label="City" placeholder="Enter City" />
        <AppInput v-model="draft.zip" label="Zip" placeholder="Enter Zip" />
      </div>
    </div>

    <!-- Spotlight Banner -->
    <div class="border-t border-line mt-7 pt-5">
      <h3 class="text-base font-bold text-ink m-0 mb-3">Spotlight Banner</h3>
      <div class="flex gap-5 mb-3">
        <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
          <input v-model="draft.spotlight_type" type="radio" value="image" class="w-4.25 h-4.25 m-0 accent-brand"> Image
        </label>
        <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
          <input v-model="draft.spotlight_type" type="radio" value="video" class="w-4.25 h-4.25 m-0 accent-brand"> Video
        </label>
      </div>
      <ImageField
        v-if="draft.spotlight_type === 'image'"
        :model-value="draft.spotlight_url || null"
        :aspect="16 / 9"
        collection="exhibitor_spotlight"
        card-width="100%"
        hint="JPG, PNG, HEIC | Max 2 MB"
        :gallery-path="`/events/${eventId}/gallery`"
        @update:model-value="onSpotlightChange"
        @uploaded="onSpotlightUploaded"
      />
      <label v-else class="uploader" style="height:150px;">
        <img v-if="draft.spotlight_url" :src="draft.spotlight_url" alt="">
        <span v-else class="text-[.88rem]">{{ spotlightUploading ? 'Uploading…' : '+ Click to upload' }}</span>
        <input type="file" accept="video/*" @change="pickSpotlight">
      </label>
    </div>

    <!-- Social Links -->
    <div class="border-t border-line mt-7 pt-5">
      <h3 class="text-base font-bold text-ink m-0 mb-4">Social Links</h3>
      <div class="flex flex-col gap-3">
        <div class="flex items-center border border-line rounded-[11px] overflow-hidden bg-white">
          <input v-model="draft.social.facebook" placeholder="Facebook URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;padding:11px 13px;">
          <div class="w-11 h-11 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-muted" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
          </div>
        </div>
        <div class="flex items-center border border-line rounded-[11px] overflow-hidden bg-white">
          <input v-model="draft.social.linkedin" placeholder="Linkedin URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;padding:11px 13px;">
          <div class="w-11 h-11 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-muted" fill="currentColor"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
          </div>
        </div>
        <div class="flex items-center border border-line rounded-[11px] overflow-hidden bg-white">
          <input v-model="draft.social.twitter" placeholder="Twitter URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;padding:11px 13px;">
          <div class="w-11 h-11 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-muted" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.858L1.999 2.25H8.056l4.261 5.638L18.244 2.25z"/></svg>
          </div>
        </div>
        <div class="flex items-center border border-line rounded-[11px] overflow-hidden bg-white">
          <input v-model="draft.social.instagram" placeholder="Instagram URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;padding:11px 13px;">
          <div class="w-11 h-11 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-muted" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
          </div>
        </div>
        <div class="flex items-center border border-line rounded-[11px] overflow-hidden bg-white">
          <span class="px-3 py-2.5 text-[.82rem] font-semibold text-muted bg-[#f7f8fa] border-r border-line whitespace-nowrap shrink-0">https://wa.me/</span>
          <input v-model="draft.social.whatsapp" placeholder="Enter WhatsApp Number" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;padding:11px 13px;">
          <div class="w-11 h-11 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </div>
        </div>
        <div class="flex items-center border border-line rounded-[11px] overflow-hidden bg-white">
          <input v-model="draft.social.youtube" placeholder="Youtube URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;padding:11px 13px;">
          <div class="w-11 h-11 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-muted" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58A2.78 2.78 0 003.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="white"/></svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Flags -->
    <div class="flex items-center gap-6 border-t border-line mt-7 pt-5">
      <AppCheckbox v-model="draft.rating" label="Rating" />
      <AppCheckbox v-model="draft.featured" label="Featured Exhibitor" />
      <AppCheckbox v-model="draft.premium" label="Premium" />
    </div>

    <!-- Contact details -->
    <div class="border-t border-line mt-7 pt-5">
      <h3 class="text-base font-bold text-ink m-0 mb-4">Contact details <span class="muted font-normal text-[.85rem]">(For internal use only)</span></h3>
      <div class="flex flex-col gap-4">
        <AppInput v-model="draft.contact.full_name" label="Full Name" placeholder="Enter Full Name" />
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
          <AppInput v-model="draft.contact.position" label="Position" placeholder="Enter Position" />
          <AppInput v-model="draft.contact.company_name" label="Company name" placeholder="Enter Company name" />
          <AppInput v-model="draft.contact.email" type="email" label="Email" placeholder="Enter Email" />
          <div>
            <label class="block mb-1.5">Mobile No</label>
            <div class="flex items-center rounded-[11px] overflow-hidden border border-[#d7dae1] bg-white focus-within:border-brand">
              <select v-model="draft.contact.phone_code" style="border:0;box-shadow:none;margin:0;border-radius:0;background:#f7f8fa;width:auto;padding:11px 8px;border-right:1px solid #d7dae1;cursor:pointer;">
                <option v-for="p in PHONE_CODES" :key="p.code" :value="p.code">{{ p.flag }} {{ p.code }}</option>
              </select>
              <input v-model="draft.contact.phone" type="tel" placeholder="Enter Mobile No" style="border:0;box-shadow:none;margin:0;border-radius:0;flex:1;outline:none;padding:11px 13px;">
            </div>
          </div>
        </div>
      </div>
    </div>

    <p v-if="error" class="error mt-4">{{ error }}</p>

    <!-- Save / Cancel -->
    <div class="flex items-center gap-3 border-t border-line mt-7 pt-5">
      <button class="btn" :disabled="saving || !draft.name.trim()" @click="save">
        {{ saving ? 'Saving…' : 'Save' }}
      </button>
      <NuxtLink :to="listPath" class="btn ghost no-underline">Cancel</NuxtLink>
    </div>
  </div>
</template>
