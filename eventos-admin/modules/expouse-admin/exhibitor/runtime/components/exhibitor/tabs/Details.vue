<script setup lang="ts">
const {
  eventId, draft, packages, editingId,
  spotlightUploading, pickSpotlight,
  tagInput, addTag, removeTag, addCta,
  error, saving, create, update, remove, drawerMode, canCreate,
} = useExhibitorContext()

const isAdd = computed(() => drawerMode.value === 'add')

// The "About" rich-text editor is a contenteditable, so its DOM lives here.
const aboutRef = ref<HTMLElement | null>(null)
function syncAbout() { if (aboutRef.value) aboutRef.value.innerHTML = draft.about || '' }
onMounted(syncAbout)
// Reflect async-loaded content, but never fight the caret while the user types.
watch(() => draft.about, () => { if (aboutRef.value && document.activeElement !== aboutRef.value) syncAbout() })
function fmtAbout(cmd: string) { document.execCommand(cmd, false); if (aboutRef.value) draft.about = aboutRef.value.innerHTML }
function onAboutInput(e: Event) { draft.about = (e.target as HTMLElement).innerHTML }

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
</script>

<template>
  <div>
    <!-- Logo uploader -->
    <div class="flex justify-center mb-5 mt-10">
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

      <AppInput
        v-model="draft.email"
        type="email"
        label="Exhibitor Email"
        :placeholder="isAdd ? 'Enter the exhibitor email' : '—'"
        :disabled="!isAdd"
        :hint="isAdd ? 'Optional — a 6-digit access code is emailed so they can sign in.' : `The admin login email can't be changed after creation.`"
      />

      <AppSelect
        v-model="draft.package_id"
        label="Package"
        placeholder="Select Package"
        :options="packageOptions"
      />
    </div>

    <label class="mt-3 block">Mobile number</label>
    <div class="flex items-center rounded-[11px] overflow-hidden border border-[#d7dae1] my-1.5 bg-white focus-within:border-brand">
      <select v-model="draft.phone_code" style="border:0;box-shadow:none;margin:0;border-radius:0;background:#f7f8fa;width:auto;padding:10px 8px;border-right:1px solid #d7dae1;cursor:pointer;">
        <option v-for="p in PHONE_CODES" :key="p.code" :value="p.code">{{ p.flag }} {{ p.code }}</option>
      </select>
      <input v-model="draft.phone" type="tel" placeholder="Enter a phone number" style="border:0;box-shadow:none;margin:0;border-radius:0;flex:1;outline:none;">
    </div>

    <div class="flex flex-col gap-3">
      <AppSelect v-model="draft.stall_no" label="Stall No" placeholder="Select Stall No" :options="STALL_OPTIONS" />

      <AppSelect v-model="draft.type" label="Type" placeholder="Select Type" :options="TYPE_OPTIONS" />
    </div>

    <!-- About (rich text) -->
    <div class="flex items-center gap-1 mt-3 mb-1">
      <label class="m-0 flex-1">About</label>
    </div>
    <div class="border border-line rounded-xl overflow-hidden my-1.5">
      <div class="flex items-center gap-0.5 px-3 py-2 bg-[#f7f8fa] border-b border-line">
        <button type="button" class="w-7 h-7 font-bold text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('bold')">B</button>
        <button type="button" class="w-7 h-7 italic text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('italic')">I</button>
        <button type="button" class="w-7 h-7 underline text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('underline')">U</button>
        <button type="button" class="w-7 h-7 line-through text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('strikeThrough')">S</button>
      </div>
      <div ref="aboutRef" contenteditable="true" class="min-h-30 p-3 text-[.93rem] text-ink outline-none" @input="onAboutInput" />
    </div>

    <div class="flex flex-col gap-3">
      <AppInput v-model="draft.street" label="Street address" placeholder="Enter Street address" />

      <AppInput v-model="draft.city" label="City" placeholder="Enter City" />

      <div class="flex gap-3">
        <div class="flex-1">
          <AppInput v-model="draft.state" label="State" placeholder="Enter State" />
        </div>
        <div class="flex-1">
          <AppInput v-model="draft.zip" label="ZIP code" placeholder="Enter Zip Code" />
        </div>
      </div>

      <AppSelect v-model="draft.country" label="Country" placeholder="Select Country" :options="COUNTRIES" />

      <AppInput v-model="draft.location_url" label="Location" placeholder="URL of the venue location" />

      <AppInput v-model="draft.website_url" label="Website" placeholder="URL of the website" />
    </div>

    <!-- Custom Tags -->
    <label class="mt-3 block">Custom Tags</label>
    <div class="border border-line rounded-[11px] px-3 pt-2 pb-1.5 my-1.5 bg-white flex flex-wrap gap-1.5 min-h-11">
      <span v-for="tag in draft.tags" :key="tag" class="inline-flex items-center gap-1 bg-brand-soft text-brand-dark text-[.8rem] font-semibold px-2.5 py-0.5 rounded-full">
        {{ tag }}
        <button type="button" class="border-0 bg-transparent cursor-pointer text-brand-dark font-bold leading-none p-0" @click="removeTag(tag)">×</button>
      </span>
      <input v-model="tagInput" placeholder="Add tag & press enter" style="border:0;box-shadow:none;margin:0;padding:0;flex:1;min-width:120px;outline:none;background:transparent;" @keydown="addTag">
    </div>

    <!-- Manage Filters -->
    <label class="mt-3 block">Manage filters</label>
    <div class="my-1.5">
      <ExhibitorFilterPicker />
    </div>

    <!-- Spotlight Banner -->
    <div class="mt-3 mb-1.5">
      <label class="block mb-2">Spotlight Banner</label>
      <div class="flex gap-5">
        <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
          <input v-model="draft.spotlight_type" type="radio" value="image" class="w-4.25 h-4.25 m-0 accent-brand"> Image
        </label>
        <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
          <input v-model="draft.spotlight_type" type="radio" value="video" class="w-4.25 h-4.25 m-0 accent-brand"> Video
        </label>
      </div>
    </div>
    <ImageField
      v-if="draft.spotlight_type === 'image'"
      :model-value="draft.spotlight_url || null"
      :aspect="16 / 9"
      collection="exhibitor_spotlight"
      card-width="100%"
      hint="Spotlight banner image"
      :gallery-path="`/events/${eventId}/gallery`"
      @update:model-value="onSpotlightChange"
      @uploaded="onSpotlightUploaded"
    />
    <label v-else class="uploader mt-1.5" style="height:130px;">
      <img v-if="draft.spotlight_url" :src="draft.spotlight_url" alt="">
      <span v-else class="text-[.88rem]">{{ spotlightUploading ? 'Uploading…' : '+ Click to upload' }}</span>
      <input type="file" accept="video/*" @change="pickSpotlight">
    </label>

    <!-- CTA -->
    <div class="flex items-center justify-between mt-4 mb-2">
      <label class="m-0 text-ink font-semibold text-[.92rem]">CTA</label>
      <button class="btn sm" @click="addCta">ADD CTA</button>
    </div>
    <div v-for="(cta, i) in draft.cta" :key="cta.id" class="border border-line rounded-xl mb-2 overflow-hidden">
      <div class="flex items-center gap-2 px-4 py-3 bg-[#f7f8fa] cursor-pointer" @click="cta.open = !cta.open">
        <span class="font-bold text-[.9rem]">CTA {{ i + 1 }}</span>
        <span class="bg-white border border-line rounded px-2 py-0.5 text-[.78rem] font-semibold">{{ cta.type }}</span>
        <div class="flex-1" />
        <button type="button" class="border-0 bg-transparent cursor-pointer text-[#dc2626] p-1" @click.stop="draft.cta.splice(i,1)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-muted transition-transform" :class="cta.open ? 'rotate-180' : ''"><path d="M6 9l6 6 6-6"/></svg>
      </div>
      <div v-if="cta.open" class="p-4 border-t border-line">
        <AppSelect v-model="cta.type" label="Type" :options="['TEXT', 'LINK', 'BUTTON']" />
        <AppInput v-model="cta.label" label="Label" placeholder="Button label" />
        <AppInput v-model="cta.value" label="Value / URL" placeholder="Link or text value" />
      </div>
    </div>

    <!-- Social Links -->
    <label class="mt-2">Social Links</label>

    <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-2 bg-white">
      <input v-model="draft.social.facebook" placeholder="Facebook URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
        <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#6b7280]" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
      </div>
    </div>

    <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-2 bg-white">
      <input v-model="draft.social.linkedin" placeholder="Linkedin URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
        <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#6b7280]" fill="currentColor"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
      </div>
    </div>

    <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-2 bg-white">
      <input v-model="draft.social.twitter" placeholder="Twitter URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
        <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#6b7280]" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.858L1.999 2.25H8.056l4.261 5.638L18.244 2.25z"/></svg>
      </div>
    </div>

    <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-2 bg-white">
      <input v-model="draft.social.instagram" placeholder="Instagram URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
        <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#6b7280]" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
      </div>
    </div>

    <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-2 bg-white">
      <span class="px-3 py-2.5 text-[.82rem] font-semibold text-muted bg-[#f7f8fa] border-r border-line whitespace-nowrap shrink-0">https://wa.me/</span>
      <input v-model="draft.social.whatsapp" placeholder="Enter WhatsApp number" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      </div>
    </div>

    <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-2 bg-white">
      <input v-model="draft.social.youtube" placeholder="YouTube URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
        <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#6b7280]" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58A2.78 2.78 0 003.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="white"/></svg>
      </div>
    </div>

    <!-- Flags row -->
    <div class="flex items-center gap-5 mt-4 mb-3">
      <AppCheckbox v-model="draft.rating" label="Rating" />
      <AppCheckbox v-model="draft.featured" label="Featured" />
      <AppCheckbox v-model="draft.premium" label="Premium" />
    </div>

    <!-- Contact details -->
    <div class="mt-3 mb-2">
      <p class="font-semibold text-[.92rem] text-ink m-0">Contact details <span class="muted font-normal">(For internal use only)</span></p>
    </div>

    <div class="flex flex-col gap-3">
      <AppInput v-model="draft.contact.full_name" label="Full name" placeholder="Enter Full name" />

      <AppInput v-model="draft.contact.company_name" label="Company name" placeholder="Enter Company name" />

      <AppInput v-model="draft.contact.position" label="Position" placeholder="Enter Position" />

      <AppInput v-model="draft.contact.email" type="email" label="Email address" placeholder="Enter Email address" />
    </div>

    <label class="mt-3 block">Mobile number</label>
    <div class="flex items-center rounded-[11px] overflow-hidden border border-[#d7dae1] my-1.5 bg-white focus-within:border-brand">
      <select v-model="draft.contact.phone_code" style="border:0;box-shadow:none;margin:0;border-radius:0;background:#f7f8fa;width:auto;padding:10px 8px;border-right:1px solid #d7dae1;cursor:pointer;">
        <option v-for="p in PHONE_CODES" :key="p.code" :value="p.code">{{ p.flag }} {{ p.code }}</option>
      </select>
      <input v-model="draft.contact.phone" type="tel" placeholder="Enter a phone number" style="border:0;box-shadow:none;margin:0;border-radius:0;flex:1;outline:none;">
    </div>

    <p v-if="error" class="error mt-2">{{ error }}</p>

    <!-- <div class="flex gap-2 pt-4 mt-2">
      <button class="btn danger px-5 py-3 text-[.92rem]" @click="remove({ id: editingId, name: draft.name })">DELETE</button>
      <button class="btn flex-1 py-3 text-[.95rem] tracking-widest" :disabled="saving || !draft.name.trim()" @click="update">
        {{ saving ? 'UPDATING…' : 'UPDATE' }}
      </button>
    </div> -->
    <div class="modal-actions border-t border-line pt-4 mt-5">
      <button class="btn ghost" @click="drawerMode = null">Cancel</button>
      <button
        v-if="isAdd"
        class="btn"
        :disabled="saving || !canCreate"
        @click="create"
      >
        {{ saving ? 'ADDING…' : 'Add Exhibitor' }}
      </button>
      <button
        v-else
        class="btn"
        :disabled="saving || !draft.name.trim()"
        @click="update"
      >
        {{ saving ? 'UPDATING…' : 'UPDATE' }}
      </button>
    </div>
  </div>
</template>
