<script setup lang="ts">
export interface LoginConfig {
  type:        string
  banner_url:  string | null
  video_url:   string
  website_url: string
}

const props = defineProps<{ eventId: string, login: LoginConfig }>()
const emit  = defineEmits<{ (e: 'update', v: Partial<LoginConfig>): void }>()

const TYPES = [
  { value: 'banner',  label: 'Banner'  },
  { value: 'video',   label: 'Video'   },
  { value: 'website', label: 'Website' },
]

const drawerOpen = ref(false)
const draft = reactive<LoginConfig>({ type: 'banner', banner_url: null, video_url: '', website_url: '' })

function openDrawer() {
  Object.assign(draft, props.login)
  drawerOpen.value = true
}

function onBannerChange(v: string | string[] | null) {
  draft.banner_url = Array.isArray(v) ? v[0] ?? null : v
}

function save() {
  emit('update', {
    banner_url:  draft.banner_url,
    video_url:   draft.video_url,
    website_url: draft.website_url,
  })
  drawerOpen.value = false
}

const drawerTitle = computed(() =>
  props.login.type === 'banner' ? 'Login Banner' : props.login.type === 'video' ? 'Login Video' : 'Login Website URL',
)
</script>

<template>
  <div>
    <!-- Section header -->
    <div class="flex items-start justify-between gap-4 mb-1">
      <div>
        <h2 class="text-[1.05rem] font-bold text-ink mb-1">Login Page Design</h2>
        <p class="text-[.85rem] text-muted">Brand-wise customization of sign-in page design for your virtual event.</p>
      </div>
      <button
        class="shrink-0 inline-flex items-center px-5 py-2.5 rounded-lg text-[.85rem] font-semibold bg-[#F0EEFD] text-brand-dark transition-colors hover:bg-brand hover:text-white cursor-pointer"
        @click="openDrawer"
      >
       Manage
      </button>
    </div>

    <!-- Design type pills (selected on the page; the sidebar edits the matching field) -->
    <div class="flex gap-4 mt-4 mb-4">
      <button
        v-for="t in TYPES" :key="t.value" type="button"
        class="flex items-center gap-2.5 px-5 py-2.5 rounded-lg border min-h-12 cursor-pointer font-medium text-[.9rem] transition-all duration-150"
        :class="login.type === t.value
          ? 'border-brand text-ink'
          : 'border-line bg-white text-ink hover:border-[#c7c2f5]'"
        @click="emit('update', { type: t.value })"
      >
        <span
          class="w-4.5 h-4.5 rounded-full border-2 shrink-0 grid place-items-center"
          :class="login.type === t.value ? 'border-brand' : 'border-[#d7dae1]'"
        >
          <span v-if="login.type === t.value" class="w-2 h-2 rounded-full bg-brand" />
        </span>
        {{ t.label }}
      </button>
    </div>

    <!-- Current value preview -->
    <div class="flex items-center gap-3">
      <template v-if="login.type === 'banner'">
        <div v-if="login.banner_url" class="w-24 rounded-lg overflow-hidden border border-line" :style="{ aspectRatio: '1796 / 1390' }">
          <img :src="login.banner_url" alt="Login banner" class="w-full h-full object-cover">
        </div>
        <span v-else class="text-[.85rem] text-muted">No banner set. Click Customise to add one.</span>
      </template>
      <span v-else-if="login.type === 'video'" class="text-[.85rem] text-muted truncate">{{ login.video_url || 'No video URL set. Click Customise to add one.' }}</span>
      <span v-else class="text-[.85rem] text-muted truncate">{{ login.website_url || 'No website URL set. Click Customise to add one.' }}</span>
    </div>

    <!-- Customise sidebar: only the field for the selected design type -->
    <Drawer v-if="drawerOpen" :title="drawerTitle" @close="drawerOpen = false">
      <div class="flex flex-col gap-5">
        <div v-if="login.type === 'banner'">
          <label class="block mb-2">Login banner image</label>
          <ImageField
            :model-value="draft.banner_url"
            :aspect="1796 / 1390"
            :output-width="1796"
            :output-height="1390"
            collection="banner"
            hint="1796×1390px recommended"
            card-width="220px"
            :gallery-path="`/events/${eventId}/gallery`"
            @update:model-value="onBannerChange"
          />
        </div>
        <AppInput
          v-else-if="login.type === 'video'"
          v-model="draft.video_url"
          label="YouTube video URL"
          placeholder="https://www.youtube.com/watch?v=…"
        />
        <AppInput
          v-else
          v-model="draft.website_url"
          label="Website URL"
          placeholder="https://yourcompany.com"
        />

        <div class="flex justify-end gap-2.5 mt-2">
          <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
          <button class="btn" @click="save">Save changes</button>
        </div>
      </div>
    </Drawer>
  </div>
</template>
