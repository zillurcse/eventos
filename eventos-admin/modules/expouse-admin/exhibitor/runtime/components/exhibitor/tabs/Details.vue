<script setup lang="ts">
import AppPhoneInput from '../../../../../core/runtime/components/AppPhoneInput.vue'

const {
  eventId, draft, packages, editingId,
  spotlightUploading, pickSpotlight,
  tagInput, addTag, removeTag,
  error, saving, create, update, remove, drawerMode, canCreate, canSave,
} = useExhibitorContext()

// AddDrawer renders this tab inside <Drawer>, which has its own sticky footer
// slot for these buttons — so it passes showFooter=false and renders them
// itself. EditPage (a plain page, not a drawer) has no such slot, so it keeps
// the footer inline here (the default).
const props = withDefaults(defineProps<{ showFooter?: boolean }>(), { showFooter: true })

const isAdd = computed(() => drawerMode.value === 'add')

// The "About" rich-text editor is a contenteditable, so its DOM lives here.
const aboutRef = ref<HTMLElement | null>(null)
function syncAbout() { if (aboutRef.value) aboutRef.value.innerHTML = draft.about || '' }
onMounted(syncAbout)
// Reflect async-loaded content, but never fight the caret while the user types.
watch(() => draft.about, () => { if (aboutRef.value && document.activeElement !== aboutRef.value) syncAbout() })
function fmtAbout(cmd: string) { document.execCommand(cmd, false); if (aboutRef.value) draft.about = aboutRef.value.innerHTML }
function onAboutInput(e: Event) { draft.about = (e.target as HTMLElement).innerHTML }
function insertLink() {
  const url = prompt('Link URL')
  if (url) { document.execCommand('createLink', false, url); if (aboutRef.value) draft.about = aboutRef.value.innerHTML }
}

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
      <AppInput v-model="draft.name" label="Exhibitor Name" placeholder="Enter the exhibitor Name" required />

      <AppInput
        v-model="draft.email"
        type="email"
        label="Exhibitor Email"
        :placeholder="isAdd ? 'Enter the exhibitor email' : '—'"
        :disabled="!isAdd"
        :required="isAdd"
        :hint="isAdd ? 'A 6-digit access code is emailed so they can sign in.' : `The admin login email can't be changed after creation.`"
      />

      <AppSelect
        v-model="draft.package_id"
        label="Package"
        placeholder="Select Package"
        :options="packageOptions"
        required
      />
    </div>

    <AppPhoneInput
      v-model:phone-code="draft.phone_code"
      v-model:phone="draft.phone"
      label="Mobile No"
      class="mt-3"
    />

    <div class="grid grid-cols-2 gap-3">
      <AppSelect v-model="draft.stall_no" label="Stall No" placeholder="Select Stall No" :options="STALL_OPTIONS" />

      <AppSelect v-model="draft.type" label="Type" placeholder="Select Type" :options="TYPE_OPTIONS" required />
    </div>

    <!-- About (rich text) -->
    <div class="flex items-center gap-1 mt-3 mb-1">
      <label class="m-0 flex-1">About</label>
    </div>
    <div class="border border-line rounded-xl overflow-hidden my-1.5 mb-2.5">
      <div class="flex items-center gap-0.5 px-3 py-2 bg-[#f7f8fa] border-b border-line">
        <button type="button" class="w-7 h-7 font-bold text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('bold')">B</button>
        <button type="button" class="w-7 h-7 italic text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('italic')">I</button>
        <button type="button" class="w-7 h-7 underline text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('underline')">U</button>
        <span class="w-px h-5 bg-line mx-0.5" />
        <button type="button" class="w-7 h-7 grid place-items-center text-ink hover:bg-line rounded" title="Bulleted list" @click="fmtAbout('insertUnorderedList')">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg>
        </button>
        <button type="button" class="w-7 h-7 grid place-items-center text-ink hover:bg-line rounded" title="Numbered list" @click="fmtAbout('insertOrderedList')">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 6h11M10 12h11M10 18h11"/><path d="M4 4v4M4 8H3M4 14a1 1 0 100 2H3M4 14v-.5a1 1 0 011-1h0a1 1 0 011 1v.5M4 16v.5a1 1 0 001 1h1"/></svg>
        </button>
        <button type="button" class="w-7 h-7 grid place-items-center text-ink hover:bg-line rounded" title="Insert link" @click="insertLink">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.07 0l1.93-1.93a5 5 0 00-7.07-7.07L10.5 5.43M14 11a5 5 0 00-7.07 0L5 12.93a5 5 0 007.07 7.07l1.43-1.43"/></svg>
        </button>
      </div>
      <div
        ref="aboutRef"
        contenteditable="true"
        class="about-area min-h-30 p-3 text-[.93rem] text-ink outline-none"
        data-ph="Let's write an awesome story!"
        @input="onAboutInput"
      />
    </div>
    <!-- <AppInput v-model="draft.venue" label="Venue" placeholder="Enter Venue" /> -->

    <AppInput v-model="draft.website_url" label="Website" placeholder="https://"  />

    <!-- Custom Tags -->
    <label class="mt-3 block">Custom Tags</label>
    <div class="border border-line rounded-lg max-h-12 px-2 pt-2 pb-1.5 my-1.5 bg-white flex items-center flex-wrap gap-1.5 min-h-12">
      <span v-for="tag in draft.tags" :key="tag" class="inline-flex items-center gap-1 bg-[#F0EEFD] text-brand-dark text-[.8rem] font-semibold px-2.5 py-0.5 rounded-md max-h-6">
        {{ tag }}
        <button type="button" class="border-0 bg-transparent cursor-pointer text-brand-dark font-bold leading-none p-0" @click="removeTag(tag)">×</button>
      </span>
      <input v-model="tagInput" placeholder="Add tag & press enter" class="h-auto text-sm" style="border:0;box-shadow:none;margin:0;padding:0;flex:1;min-width:120px;outline:none;background:transparent;" @keydown="addTag">
    </div>

    <!-- Manage Filters -->
    <label class="mt-3 block">Manage Filters</label>
    <div class="my-1.5">
      <ExhibitorFilterPicker />
    </div>

    <!-- Location -->
    <div class="border-t border-line my-5" />
    <p class="font-bold text-[1.05rem] text-ink m-0 mb-3">Location</p>
    <div class="flex flex-col gap-3">
      <div class="grid grid-cols-2 gap-3">
        <AppInput v-model="draft.venue" label="Venue" placeholder="Enter Venue" />
        <AppInput v-model="draft.street" label="Street" placeholder="Enter Street" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <AppInput v-model="draft.address_line1" label="Address Line 1" placeholder="Enter Address Line 1" />
        <AppInput v-model="draft.address_line2" label="Address Line 2" placeholder="Enter Address Line 2" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <AppSelect v-model="draft.country" label="Country" placeholder="Select Country" :options="COUNTRIES" searchable />
        <AppInput v-model="draft.state" label="State" placeholder="Enter State" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <AppInput v-model="draft.city" label="City" placeholder="Enter City" />
        <AppInput v-model="draft.zip" label="Zip" placeholder="Enter Zip Code" />
      </div>
      <AppInput v-model="draft.location_url" label="Location URL" placeholder="URL of the venue location (optional)" />
    </div>

    <!-- Spotlight Banner -->
    <div class="border-t border-line my-5" />
    <div class="mb-1.5">
      <p class="font-bold text-[1.05rem] text-ink m-0 mb-3">Spotlight Banner</p>
      <div class="grid grid-cols-2 gap-4 mb-3">
        <label
          class="flex items-center gap-2.5 px-4 py-3 rounded-lg border cursor-pointer text-[.92rem] font-medium transition-colors"
          :class="draft.spotlight_type === 'image' ? 'border-brand bg-[#F0EEFD] text-brand-dark' : 'border-line text-ink'"
        >
          <input v-model="draft.spotlight_type" type="radio" value="image" class="sr-only">
          <span class="w-5 h-5 rounded-full border-2 grid place-items-center shrink-0" :class="draft.spotlight_type === 'image' ? 'border-brand' : 'border-[#cdd2dc]'">
            <span v-if="draft.spotlight_type === 'image'" class="w-2.5 h-2.5 rounded-full bg-brand" />
          </span>
          Image
        </label>
        <label
          class="flex items-center gap-2.5 px-4 py-3 rounded-lg border cursor-pointer text-[.92rem] font-medium transition-colors"
          :class="draft.spotlight_type === 'video' ? 'border-brand bg-[#F0EEFD] text-brand-dark' : 'border-line text-ink'"
        >
          <input v-model="draft.spotlight_type" type="radio" value="video" class="sr-only">
          <span class="w-5 h-5 rounded-full border-2 grid place-items-center shrink-0" :class="draft.spotlight_type === 'video' ? 'border-brand' : 'border-[#cdd2dc]'">
            <span v-if="draft.spotlight_type === 'video'" class="w-2.5 h-2.5 rounded-full bg-brand" />
          </span>
          Video
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

    <ExhibitorCtaEditor />

    <!-- Social Links -->
    <div class="border-t border-line my-5" />
    <p class="font-bold text-lg text-ink m-0 mb-3">Social Links</p>

    <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
      <input v-model="draft.social.facebook" placeholder="Facebook URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="22" viewBox="0 0 13 22" fill="none">
          <path d="M12 1H9C7.67392 1 6.40215 1.52678 5.46447 2.46447C4.52678 3.40215 4 4.67392 4 6V9H1V13H4V21H8V13H11L12 9H8V6C8 5.73478 8.10536 5.48043 8.29289 5.29289C8.48043 5.10536 8.73478 5 9 5H12V1Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    </div>

    <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
      <input v-model="draft.social.linkedin" placeholder="LinkedIn URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
          <path d="M6 9H2V21H6V9Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M16 8C17.5913 8 19.1174 8.63214 20.2426 9.75736C21.3679 10.8826 22 12.4087 22 14V21H18V14C18 13.4696 17.7893 12.9609 17.4142 12.5858C17.0391 12.2107 16.5304 12 16 12C15.4696 12 14.9609 12.2107 14.5858 12.5858C14.2107 12.9609 14 13.4696 14 14V21H10V14C10 12.4087 10.6321 10.8826 11.7574 9.75736C12.8826 8.63214 14.4087 8 16 8V8Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M4 6C5.10457 6 6 5.10457 6 4C6 2.89543 5.10457 2 4 2C2.89543 2 2 2.89543 2 4C2 5.10457 2.89543 6 4 6Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    </div>

    <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
      <input v-model="draft.social.twitter" placeholder="Twitter URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="18" viewBox="0 0 20 18" fill="none">
            <path d="M15.7512 0H18.818L12.1179 7.62462L20 18H13.8284L8.99458 11.7074L3.46359 18H0.394938L7.5613 9.84461L0 0H6.32828L10.6976 5.75169L15.7512 0ZM14.6748 16.1723H16.3742L5.4049 1.73169H3.58133L14.6748 16.1723Z" fill="#64676A"/>
        </svg>
      </div>
    </div>

    <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
      <input v-model="draft.social.instagram" placeholder="Instagram URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M17 2H7C4.23858 2 2 4.23858 2 7V17C2 19.7614 4.23858 22 7 22H17C19.7614 22 22 19.7614 22 17V7C22 4.23858 19.7614 2 17 2Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M16 11.3703C16.1234 12.2025 15.9812 13.0525 15.5937 13.7993C15.2062 14.5461 14.5931 15.1517 13.8416 15.53C13.0901 15.9082 12.2384 16.0399 11.4077 15.9062C10.5771 15.7726 9.80971 15.3804 9.21479 14.7855C8.61987 14.1905 8.22768 13.4232 8.09402 12.5925C7.96035 11.7619 8.09202 10.9102 8.47028 10.1587C8.84854 9.40716 9.45414 8.79404 10.2009 8.40654C10.9477 8.01904 11.7977 7.87689 12.63 8.0003C13.4789 8.12619 14.2648 8.52176 14.8716 9.12861C15.4785 9.73545 15.8741 10.5214 16 11.3703Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M17.5 6.5H17.51" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
         </svg>
      </div>
    </div>

    <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
      <span class="px-3 py-2.5 text-[.82rem] font-semibold text-muted bg-[#f7f8fa] border-r border-line whitespace-nowrap shrink-0">https://wa.me/</span>
      <input v-model="draft.social.whatsapp" placeholder="Enter WhatsApp number" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M16.0402 13.1826C15.7672 13.0461 14.4209 12.386 14.17 12.2958C13.9191 12.2045 13.7367 12.1594 13.5544 12.4322C13.372 12.704 12.847 13.319 12.6867 13.5005C12.5275 13.6821 12.3673 13.7041 12.0943 13.5677C11.2874 13.2479 10.5428 12.7907 9.89359 12.2166C9.29487 11.6657 8.78158 11.0294 8.37048 10.3285C8.21131 10.0557 8.3539 9.90826 8.49096 9.77293C8.61365 9.6508 8.76507 9.45496 8.90103 9.29542C9.01363 9.15769 9.10593 9.00469 9.17514 8.84102C9.21156 8.76581 9.22852 8.68273 9.22446 8.59934C9.22041 8.51594 9.19548 8.43488 9.15193 8.36352C9.0834 8.22709 8.53628 6.88589 8.30858 6.34017C8.08642 5.80986 7.86093 5.88137 7.69182 5.87257C7.53266 5.86487 7.35028 5.86267 7.16791 5.86267C7.02917 5.86652 6.89274 5.89891 6.7672 5.9578C6.64165 6.01668 6.52971 6.1008 6.4384 6.20485C6.12895 6.49656 5.88392 6.84923 5.71893 7.2404C5.55394 7.63157 5.4726 8.05266 5.48011 8.47684C5.56886 9.50473 5.95724 10.4844 6.59757 11.2957C7.77131 13.0473 9.38253 14.465 11.273 15.4095C11.783 15.6275 12.3039 15.8192 12.8337 15.9838C13.3923 16.1526 13.9827 16.1891 14.558 16.0905C14.939 16.0137 15.2998 15.859 15.6177 15.6363C15.9356 15.4136 16.2037 15.1277 16.4049 14.7966C16.5845 14.3895 16.6398 13.9388 16.5641 13.5005C16.4967 13.3861 16.3143 13.319 16.0402 13.1826V13.1826ZM18.7946 3.19349C16.9155 1.32346 14.4178 0.196881 11.7664 0.0234775C9.11503 -0.149926 6.49069 0.641668 4.38197 2.25088C2.27324 3.8601 0.823849 6.17728 0.303621 8.77101C-0.216607 11.3647 0.227784 14.0583 1.55406 16.3502L0 21.9999L5.80728 20.4849C7.41346 21.3554 9.21315 21.8116 11.042 21.8118H11.0464C13.2129 21.8107 15.3305 21.1704 17.1316 19.9718C18.9327 18.7733 20.3365 17.0702 21.1656 15.0778C21.9948 13.0854 22.2121 10.8931 21.7901 8.77785C21.3681 6.66258 20.3257 4.71928 18.7946 3.19348V3.19349ZM15.8843 18.5847C14.4343 19.4895 12.7577 19.9695 11.0464 19.9699H11.042C9.41157 19.9699 7.81118 19.5332 6.40856 18.7058L6.07586 18.5099L2.62952 19.4099L3.54914 16.0652L3.3336 15.7219C2.37701 14.2034 1.89425 12.4366 1.94637 10.645C1.99849 8.85342 2.58315 7.11742 3.62642 5.65656C4.66969 4.1957 6.12471 3.07559 7.80749 2.43786C9.49027 1.80013 11.3252 1.67342 13.0804 2.07377C14.8355 2.47411 16.4319 3.38352 17.6678 4.68701C18.9037 5.99049 19.7236 7.6295 20.0237 9.39679C20.3239 11.1641 20.0908 12.9803 19.354 14.6157C18.6172 16.2512 17.4097 17.6324 15.8843 18.5847" fill="#64676A"/>
        </svg>
      </div>
    </div>

    <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
      <input v-model="draft.social.youtube" placeholder="YouTube URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
      <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
        <svg xmlns="http://www.w3.org/2000/svg" width="21" height="15" viewBox="0 0 21 15" fill="none">
          <path d="M20.1747 4.99738C20.2201 3.68531 19.9331 2.38306 19.3406 1.21154C18.9385 0.730842 18.3806 0.406444 17.7639 0.294876C15.2133 0.0634411 12.6522 -0.0314165 10.0914 0.0107097C7.54001 -0.0333285 4.98806 0.0584682 2.44641 0.28571C1.94392 0.377117 1.47889 0.612815 1.10808 0.964043C0.283081 1.72488 0.191415 3.02654 0.0997479 4.12654C-0.0332493 6.10431 -0.0332493 8.08878 0.0997479 10.0665C0.126267 10.6857 0.218451 11.3002 0.374748 11.8999C0.485275 12.3629 0.708894 12.7912 1.02558 13.1465C1.39891 13.5164 1.87477 13.7655 2.39141 13.8615C4.36767 14.1055 6.35895 14.2066 8.34975 14.164C11.5581 14.2099 14.3722 14.164 17.6997 13.9074C18.2291 13.8172 18.7183 13.5678 19.1022 13.1924C19.3589 12.9356 19.5506 12.6214 19.6614 12.2757C19.9892 11.2698 20.1503 10.217 20.1381 9.15904C20.1747 8.64571 20.1747 5.54738 20.1747 4.99738ZM8.01975 9.70904V4.03488L13.4464 6.88571C11.9247 7.72904 9.91725 8.68238 8.01975 9.70904Z" fill="#64676A"/>
        </svg>
      </div>
    </div>

    <!-- Flags -->
    <div class="border-t border-line my-5" />
    <div class="flex flex-col gap-3 mb-3">
      <AppCheckbox v-model="draft.rating" label="Rating" />
      <AppCheckbox v-model="draft.featured" label="Featured Exhibitor" />
      <AppCheckbox v-model="draft.premium" label="Premium" />
    </div>

    <!-- Contact details -->
    <div class="border-t border-line my-5" />
    <div class="mt-4 mb-2">
      <p class="font-bold text-lg text-ink m-0">Contact details <span class="muted font-normal text-sm">(For internal use only)</span></p>
    </div>

    <div class="flex flex-col gap-3">
      <AppInput v-model="draft.contact.full_name" label="Full Name" placeholder="Enter Full Name" required />

      <AppInput v-model="draft.contact.position" label="Position" placeholder="Enter Position" required />

      <AppInput v-model="draft.contact.company_name" label="Company name" placeholder="Enter Company name" required />

      <AppInput v-model="draft.contact.email" type="email" label="Email" placeholder="Enter Email" required />
    </div>

    <AppPhoneInput
      v-model:phone-code="draft.contact.phone_code"
      v-model:phone="draft.contact.phone"
      label="Mobile No"
      class="mt-3"
      required
    />

    <p v-if="error" class="error mt-2">{{ error }}</p>

    <div v-if="props.showFooter" class="modal-actions border-t border-line pt-4 mt-5 justify-start">
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
        :disabled="saving || !canSave"
        @click="update"
      >
        {{ saving ? 'UPDATING…' : 'UPDATE' }}
      </button>
      <button class="btn ghost" @click="drawerMode = null">Cancel</button>
    </div>
  </div>
</template>

<style scoped>
.about-area:empty::before {
  content: attr(data-ph);
  color: var(--faint);
  font-style: italic;
}
</style>
